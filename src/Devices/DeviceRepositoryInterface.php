<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

interface DeviceRepositoryInterface
{
    /**
     * @return list<Device>
     */
    public function allOwnedBy(int $ownerUserId): array;

    public function findOwnedBy(
        int $deviceId,
        int $ownerUserId
    ): ?Device;

    public function findOwnedByForUpdate(
        int $deviceId,
        int $ownerUserId
    ): ?Device;

    public function create(
        int $ownerUserId,
        string $name,
        ?string $vendor,
        ?string $model
    ): int;

    public function updateIdentity(
        int $deviceId,
        int $ownerUserId,
        string $name,
        ?string $vendor,
        ?string $model
    ): bool;

    public function setActive(
        int $deviceId,
        int $ownerUserId,
        bool $active
    ): bool;
}
