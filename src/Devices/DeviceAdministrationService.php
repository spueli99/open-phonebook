<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Database\TransactionManagerInterface;
use RuntimeException;

final class DeviceAdministrationService
{
    public function __construct(
        private readonly DeviceRepositoryInterface $devices,
        private readonly DeviceCredentialRepositoryInterface $credentials,
        private readonly DeviceAddressBookRepositoryInterface $assignments,
        private readonly AddressBookAccessService $addressBookAccess,
        private readonly TransactionManagerInterface $transactions,
        private readonly string $applicationKey,
    ) {
    }

    /**
     * @return list<Device>
     */
    public function allOwnedBy(int $ownerUserId): array
    {
        return $this->devices->allOwnedBy($ownerUserId);
    }

    /**
     * @return list<\OpenPhonebook\Contacts\AddressBookAccess>
     */
    public function accessibleAddressBooks(int $ownerUserId): array
    {
        return $this->addressBookAccess->allAccessibleBy($ownerUserId);
    }

    /**
     * @return list<int>
     */
    public function addressBookIds(
        int $ownerUserId,
        int $deviceId
    ): array {
        $this->requireOwnedDevice($ownerUserId, $deviceId);

        return $this->assignments->addressBookIdsForDevice($deviceId);
    }

    public function create(
        int $ownerUserId,
        string $name,
        ?string $vendor,
        ?string $model
    ): DeviceCredentialCreationResult {
        [$name, $vendor, $model] = $this->validateIdentity(
            $name,
            $vendor,
            $model
        );
        $token = DeviceCredentialToken::generate(
            $this->applicationKey
        );

        return $this->transactions->transactional(
            function () use (
                $ownerUserId,
                $name,
                $vendor,
                $model,
                $token
            ): DeviceCredentialCreationResult {
                $deviceId = $this->devices->create(
                    $ownerUserId,
                    $name,
                    $vendor,
                    $model
                );
                $this->credentials->create(
                    $deviceId,
                    DeviceCredentialScope::PhonebookRead,
                    $token->hash
                );

                $device = $this->devices->findOwnedBy(
                    $deviceId,
                    $ownerUserId
                );

                if ($device === null) {
                    throw new RuntimeException(
                        'Created device could not be loaded.'
                    );
                }

                return new DeviceCredentialCreationResult(
                    $device,
                    DeviceCredentialScope::PhonebookRead,
                    $token->plainText,
                );
            }
        );
    }

    public function rotateCredential(
        int $ownerUserId,
        int $deviceId
    ): DeviceCredentialCreationResult {
        $token = DeviceCredentialToken::generate(
            $this->applicationKey
        );

        return $this->transactions->transactional(
            function () use (
                $ownerUserId,
                $deviceId,
                $token
            ): DeviceCredentialCreationResult {
                $device = $this->devices->findOwnedByForUpdate(
                    $deviceId,
                    $ownerUserId
                );

                if ($device === null) {
                    throw new RuntimeException('Device not found.');
                }

                if (!$device->isActive) {
                    throw new RuntimeException(
                        'Inactive device credentials cannot be rotated.'
                    );
                }

                $this->credentials->revokeActive(
                    $deviceId,
                    DeviceCredentialScope::PhonebookRead
                );
                $this->credentials->create(
                    $deviceId,
                    DeviceCredentialScope::PhonebookRead,
                    $token->hash
                );

                return new DeviceCredentialCreationResult(
                    $device,
                    DeviceCredentialScope::PhonebookRead,
                    $token->plainText,
                );
            }
        );
    }

    public function revokeCredential(
        int $ownerUserId,
        int $deviceId
    ): void {
        $this->transactions->transactional(
            function () use ($ownerUserId, $deviceId): void {
                if (
                    $this->devices->findOwnedByForUpdate(
                        $deviceId,
                        $ownerUserId
                    ) === null
                ) {
                    throw new RuntimeException('Device not found.');
                }

                $this->credentials->revokeActive(
                    $deviceId,
                    DeviceCredentialScope::PhonebookRead
                );
            }
        );
    }

    public function setActive(
        int $ownerUserId,
        int $deviceId,
        bool $active
    ): void {
        $this->transactions->transactional(
            function () use (
                $ownerUserId,
                $deviceId,
                $active
            ): void {
                $device = $this->devices->findOwnedByForUpdate(
                    $deviceId,
                    $ownerUserId
                );

                if ($device === null) {
                    throw new RuntimeException('Device not found.');
                }

                if ($device->isActive === $active) {
                    return;
                }

                if (
                    !$this->devices->setActive(
                        $deviceId,
                        $ownerUserId,
                        $active
                    )
                ) {
                    throw new RuntimeException(
                        'Device state could not be changed.'
                    );
                }

                if (!$active) {
                    $this->credentials->revokeActive(
                        $deviceId,
                        DeviceCredentialScope::PhonebookRead
                    );
                }
            }
        );
    }

    /**
     * @param list<int> $addressBookIds
     */
    public function replaceAddressBooks(
        int $ownerUserId,
        int $deviceId,
        array $addressBookIds
    ): void {
        $this->requireOwnedDevice($ownerUserId, $deviceId);
        $addressBookIds = array_values(
            array_unique($addressBookIds)
        );
        sort($addressBookIds);

        foreach ($addressBookIds as $addressBookId) {
            $this->addressBookAccess->requireReadable(
                $addressBookId,
                $ownerUserId
            );
        }

        $this->assignments->replaceAssignments(
            $deviceId,
            $addressBookIds
        );
    }

    public function hasActiveCredential(
        int $ownerUserId,
        int $deviceId
    ): bool {
        $this->requireOwnedDevice($ownerUserId, $deviceId);

        return $this->credentials->hasActive(
            $deviceId,
            DeviceCredentialScope::PhonebookRead
        );
    }

    private function requireOwnedDevice(
        int $ownerUserId,
        int $deviceId
    ): Device {
        $device = $this->devices->findOwnedBy(
            $deviceId,
            $ownerUserId
        );

        if ($device === null) {
            throw new RuntimeException('Device not found.');
        }

        return $device;
    }

    /**
     * @return array{string, ?string, ?string}
     */
    private function validateIdentity(
        string $name,
        ?string $vendor,
        ?string $model
    ): array {
        $name = trim($name);
        $vendor = $this->optionalText($vendor);
        $model = $this->optionalText($model);

        if ($name === '' || mb_strlen($name) > 120) {
            throw new RuntimeException('Invalid device name.');
        }

        if (
            ($vendor !== null && mb_strlen($vendor) > 120)
            || ($model !== null && mb_strlen($model) > 120)
        ) {
            throw new RuntimeException(
                'Device vendor or model is too long.'
            );
        }

        return [$name, $vendor, $model];
    }

    private function optionalText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
