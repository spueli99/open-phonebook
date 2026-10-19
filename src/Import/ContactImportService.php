<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\ContactPhoneNumberInput;
use OpenPhonebook\Contacts\ContactRepositoryInterface;
use OpenPhonebook\Contacts\PhoneNumberNormalizerInterface;
use OpenPhonebook\Database\TransactionManagerInterface;
use RuntimeException;

final class ContactImportService
{
    public function __construct(
        private readonly AddressBookAccessService $addressBookAccess,
        private readonly ContactImportParserInterface $parser,
        private readonly ContactImportDraftStoreInterface $drafts,
        private readonly ContactRepositoryInterface $contacts,
        private readonly PhoneNumberNormalizerInterface $phoneNumberNormalizer,
        private readonly ImportDuplicateDetector $duplicates,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    /**
     * @param list<int> $forceDuplicateRows
     */
    public function import(
        string $token,
        int $addressBookId,
        int $userId,
        array $forceDuplicateRows = []
    ): ContactImportResult {
        $this->addressBookAccess->requireWritable(
            $addressBookId,
            $userId
        );

        $draft = $this->drafts->resolve(
            $token,
            $addressBookId,
            $userId
        );

        if ($draft === null) {
            throw new RuntimeException(
                'Import draft is missing, invalid, or expired.'
            );
        }

        $preview = $this->parser->parse($draft->contents);

        if ($preview->contacts === []) {
            throw new RuntimeException(
                'Import contains no importable contacts.'
            );
        }

        $candidates = $this->duplicates->detect(
            $addressBookId,
            $preview->contacts
        );
        $forcedRows = array_fill_keys($forceDuplicateRows, true);

        [$importedContacts, $skippedDuplicates] =
            $this->transactions->transactional(
                function () use (
                    $candidates,
                    $forcedRows,
                    $addressBookId
                ): array {
                    $count = 0;
                    $skipped = 0;

                    foreach ($candidates as $candidate) {
                        if (
                            $candidate->duplicateStatus->isDuplicate()
                            && !isset($forcedRows[$candidate->sourceRow])
                        ) {
                            $skipped++;
                            continue;
                        }

                        $contact = $candidate->contact;
                    $phoneNumbers = [];

                    foreach ($contact->phoneNumbers as $phoneNumber) {
                        $phoneNumbers[] = new ContactPhoneNumberInput(
                            $phoneNumber->label,
                            $phoneNumber->rawNumber,
                            $this->phoneNumberNormalizer->normalize(
                                $phoneNumber->rawNumber
                            ),
                        );
                    }

                    $this->contacts->create(
                        $addressBookId,
                        $contact->displayName,
                        $contact->givenName,
                        $contact->familyName,
                        $contact->organizationName,
                        $contact->notes,
                        $phoneNumbers,
                    );
                    $count++;
                    }

                    return [$count, $skipped];
                }
            );

        $this->drafts->discard($token);

        return new ContactImportResult(
            $importedContacts,
            $skippedDuplicates
        );
    }
}
