<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\ContactRepositoryInterface;
use OpenPhonebook\Contacts\PhoneNumberNormalizerInterface;
use OpenPhonebook\Devices\AuthenticatedDevice;
use OpenPhonebook\Devices\DeviceAddressBookRepositoryInterface;

final class CallerLookupService
{
    public function __construct(
        private readonly DeviceAddressBookRepositoryInterface $assignments,
        private readonly AddressBookAccessService $access,
        private readonly ContactRepositoryInterface $contacts,
        private readonly PhoneNumberNormalizerInterface $normalizer,
    ) {
    }

    public function findDisplayName(
        AuthenticatedDevice $device,
        ?string $remoteNumber
    ): ?string {
        if ($remoteNumber === null || trim($remoteNumber) === '') {
            return null;
        }

        $normalized = $this->normalizer->normalize($remoteNumber);

        if ($normalized === null) {
            return null;
        }

        $assigned = array_fill_keys(
            $this->assignments->addressBookIdsForDevice($device->deviceId),
            true
        );

        foreach ($this->access->allAccessibleBy($device->ownerUserId) as $book) {
            $bookId = $book->addressBook->id;

            if (!isset($assigned[$bookId])) {
                continue;
            }

            $matches = $this->contacts->findByNormalizedPhoneNumbers(
                $bookId,
                [$normalized]
            );

            if ($matches !== []) {
                return $matches[0]->displayName;
            }
        }

        return null;
    }
}
