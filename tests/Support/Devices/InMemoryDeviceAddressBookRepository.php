<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Devices;

use OpenPhonebook\Devices\DeviceAddressBookRepositoryInterface;

final class InMemoryDeviceAddressBookRepository implements DeviceAddressBookRepositoryInterface
{
    /**
     * @var array<int, list<int>>
     */
    private array $assignments = [];

    public function addressBookIdsForDevice(int $deviceId): array
    {
        return $this->assignments[$deviceId] ?? [];
    }

    public function replaceAssignments(
        int $deviceId,
        array $addressBookIds
    ): void {
        $this->assignments[$deviceId] = array_values(
            $addressBookIds
        );
    }
}
