<?php

declare(strict_types=1);

namespace OpenPhonebook\Exports;

use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\ContactRepositoryInterface;
use OpenPhonebook\Devices\AuthenticatedDevice;
use OpenPhonebook\Devices\DeviceAddressBookRepositoryInterface;
use RuntimeException;

final class DevicePhonebookService
{
    private const MAX_CONTACTS = 5000;

    public function __construct(
        private readonly DeviceAddressBookRepositoryInterface $assignments,
        private readonly AddressBookAccessService $addressBookAccess,
        private readonly ContactRepositoryInterface $contacts,
    ) {
    }

    /**
     * @return list<PhonebookEntry>
     */
    public function entriesFor(AuthenticatedDevice $device): array
    {
        $assigned = array_fill_keys(
            $this->assignments->addressBookIdsForDevice(
                $device->deviceId
            ),
            true
        );
        $entries = [];

        foreach (
            $this->addressBookAccess->allAccessibleBy(
                $device->ownerUserId
            ) as $access
        ) {
            $addressBookId = $access->addressBook->id;

            if (!isset($assigned[$addressBookId])) {
                continue;
            }

            foreach (
                $this->contacts->allInAddressBook($addressBookId) as $contact
            ) {
                $phoneNumbers = [];

                foreach ($contact->phoneNumbers as $phoneNumber) {
                    $number = $phoneNumber->normalizedE164
                        ?? trim($phoneNumber->rawNumber);

                    if ($number === '') {
                        continue;
                    }

                    $phoneNumbers[] = new PhonebookPhoneNumber(
                        $number,
                        $phoneNumber->label
                    );
                }

                if ($phoneNumbers === []) {
                    continue;
                }

                $entries[] = new PhonebookEntry(
                    $contact->displayName,
                    $phoneNumbers
                );

                if (count($entries) > self::MAX_CONTACTS) {
                    throw new RuntimeException(
                        'Phonebook exceeds the supported contact limit.'
                    );
                }
            }
        }

        usort(
            $entries,
            static fn (
                PhonebookEntry $left,
                PhonebookEntry $right
            ): int => strcasecmp(
                $left->displayName,
                $right->displayName
            )
        );

        return $entries;
    }
}
