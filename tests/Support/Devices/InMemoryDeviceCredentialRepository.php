<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Devices;

use DateTimeImmutable;
use DateTimeZone;
use OpenPhonebook\Devices\AuthenticatedDevice;
use OpenPhonebook\Devices\DeviceCredentialRepositoryInterface;
use OpenPhonebook\Devices\DeviceCredentialScope;

final class InMemoryDeviceCredentialRepository implements DeviceCredentialRepositoryInterface
{
    /**
     * @var array<string, array{
     *     device_id: int,
     *     scope: DeviceCredentialScope,
     *     id: int,
     *     active: bool,
     *     owner_user_id: int,
     *     last_used_at: ?DateTimeImmutable
     * }>
     */
    private array $credentials = [];
    private int $nextCredentialId = 1;

    /**
     * @var array<int, int>
     */
    private array $owners = [];

    public function setOwner(int $deviceId, int $ownerUserId): void
    {
        $this->owners[$deviceId] = $ownerUserId;
    }

    public function create(
        int $deviceId,
        DeviceCredentialScope $scope,
        string $tokenHash
    ): void {
        $this->credentials[$tokenHash] = [
            'device_id' => $deviceId,
            'scope' => $scope,
            'id' => $this->nextCredentialId++,
            'active' => true,
            'owner_user_id' => $this->owners[$deviceId] ?? 0,
            'last_used_at' => null,
        ];
    }

    public function revokeActive(
        int $deviceId,
        DeviceCredentialScope $scope
    ): void {
        foreach ($this->credentials as $hash => $credential) {
            if (
                $credential['device_id'] === $deviceId
                && $credential['scope'] === $scope
            ) {
                $this->credentials[$hash]['active'] = false;
            }
        }
    }

    public function hasActive(
        int $deviceId,
        DeviceCredentialScope $scope
    ): bool {
        foreach ($this->credentials as $credential) {
            if (
                $credential['device_id'] === $deviceId
                && $credential['scope'] === $scope
                && $credential['active']
            ) {
                return true;
            }
        }

        return false;
    }

    public function findActiveByTokenHash(
        string $tokenHash,
        DeviceCredentialScope $scope
    ): ?AuthenticatedDevice {
        $credential = $this->credentials[$tokenHash] ?? null;

        if (
            $credential === null
            || !$credential['active']
            || $credential['scope'] !== $scope
        ) {
            return null;
        }

        return new AuthenticatedDevice(
            $credential['id'],
            $credential['device_id'],
            $credential['owner_user_id'],
            $scope,
        );
    }

    public function markLastUsed(int $credentialId): void
    {
        foreach ($this->credentials as $hash => $credential) {
            if (
                $credential['id'] === $credentialId
                && $credential['active']
            ) {
                $this->credentials[$hash]['last_used_at'] =
                    new DateTimeImmutable('now', new DateTimeZone('UTC'));

                return;
            }
        }
    }

    public function activeLastUsedAt(
        int $deviceId,
        DeviceCredentialScope $scope
    ): ?DateTimeImmutable {
        foreach ($this->credentials as $credential) {
            if (
                $credential['device_id'] === $deviceId
                && $credential['scope'] === $scope
                && $credential['active']
            ) {
                return $credential['last_used_at'];
            }
        }

        return null;
    }

    public function containsPlainText(string $plainText): bool
    {
        return array_key_exists($plainText, $this->credentials);
    }
}
