<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Devices;

use OpenPhonebook\Devices\Device;
use OpenPhonebook\Devices\DeviceRepositoryInterface;

final class InMemoryDeviceRepository implements DeviceRepositoryInterface
{
    /**
     * @var array<int, Device>
     */
    private array $devices = [];

    private int $nextId = 1;

    public function allOwnedBy(int $ownerUserId): array
    {
        return array_values(
            array_filter(
                $this->devices,
                static fn (Device $device): bool =>
                    $device->ownerUserId === $ownerUserId
            )
        );
    }

    public function findOwnedBy(
        int $deviceId,
        int $ownerUserId
    ): ?Device {
        $device = $this->devices[$deviceId] ?? null;

        if (
            $device === null
            || $device->ownerUserId !== $ownerUserId
        ) {
            return null;
        }

        return $device;
    }

    public function create(
        int $ownerUserId,
        string $name,
        ?string $vendor,
        ?string $model
    ): int {
        $id = $this->nextId++;
        $this->devices[$id] = new Device(
            $id,
            $ownerUserId,
            $name,
            $vendor,
            $model,
            true
        );

        return $id;
    }

    public function setActive(
        int $deviceId,
        int $ownerUserId,
        bool $active
    ): bool {
        $device = $this->findOwnedBy(
            $deviceId,
            $ownerUserId
        );

        if ($device === null) {
            return false;
        }

        $this->devices[$deviceId] = new Device(
            $device->id,
            $device->ownerUserId,
            $device->name,
            $device->vendor,
            $device->model,
            $active
        );

        return true;
    }
}
