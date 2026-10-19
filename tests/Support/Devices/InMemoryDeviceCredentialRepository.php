<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Devices;

use OpenPhonebook\Devices\AuthenticatedDevice;
use OpenPhonebook\Devices\DeviceCredentialRepositoryInterface;
use OpenPhonebook\Devices\DeviceCredentialScope;

final class InMemoryDeviceCredentialRepository implements DeviceCredentialRepositoryInterface
{
    /**
     * @var array<string, array{
     *     device_id: int,
     *     scope: DeviceCredentialScope,
     *     active: bool,
     *     owner_user_id: int
     * }>
     */
    private array $credentials = [];

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
            'active' => true,
            'owner_user_id' => $this->owners[$deviceId] ?? 0,
        ];
    }

    public function revokeActive(
        int $deviceId,
        DeviceCredentialScope $scope
    ): void {
        foreach ($this->credentials as $hash => $credential) {
            if ($credential['device_id'] === $deviceId) {
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
        ) {
            return null;
        }

        return new AuthenticatedDevice(
            $credential['device_id'],
            $credential['owner_user_id'],
            $scope,
        );
    }

    public function containsPlainText(string $plainText): bool
    {
        return array_key_exists($plainText, $this->credentials);
    }
}
