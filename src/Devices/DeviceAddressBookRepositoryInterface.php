<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

interface DeviceAddressBookRepositoryInterface
{
    /**
     * @return list<int>
     */
    public function addressBookIdsForDevice(int $deviceId): array;

    /**
     * @param list<int> $addressBookIds
     */
    public function replaceAssignments(
        int $deviceId,
        array $addressBookIds
    ): void;
}
