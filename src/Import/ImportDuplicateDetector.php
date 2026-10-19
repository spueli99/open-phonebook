<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

use OpenPhonebook\Contacts\ContactRepositoryInterface;
use OpenPhonebook\Contacts\PhoneNumberNormalizerInterface;

final class ImportDuplicateDetector
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly PhoneNumberNormalizerInterface $normalizer,
    ) {
    }

    /**
     * @param list<ImportPreviewContact> $contacts
     * @return list<ImportPreviewCandidate>
     */
    public function detect(
        int $addressBookId,
        array $contacts
    ): array {
        $existingNumbers = array_fill_keys(
            $this->contacts->normalizedPhoneNumbersInAddressBook(
                $addressBookId
            ),
            true
        );
        $seenImportNumbers = [];
        $result = [];

        foreach ($contacts as $contact) {
            $normalizedNumbers = [];

            foreach ($contact->phoneNumbers as $phoneNumber) {
                $normalized = $this->normalizer->normalize(
                    $phoneNumber->rawNumber
                );

                if ($normalized !== null) {
                    $normalizedNumbers[$normalized] = true;
                }
            }

            $status = ImportDuplicateStatus::None;

            foreach (array_keys($normalizedNumbers) as $normalized) {
                if (isset($existingNumbers[$normalized])) {
                    $status = ImportDuplicateStatus::ExistingContact;
                    break;
                }

                if (isset($seenImportNumbers[$normalized])) {
                    $status = ImportDuplicateStatus::WithinImport;
                }
            }

            foreach (array_keys($normalizedNumbers) as $normalized) {
                $seenImportNumbers[$normalized] = true;
            }

            $result[] = new ImportPreviewCandidate(
                $contact->sourceRow,
                $contact,
                $status,
            );
        }

        return $result;
    }
}
