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
        private readonly ImportContactMerger $merger,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    /**
     * @param array<int, ImportDuplicateDecision> $duplicateDecisions
     */
    public function import(
        string $token,
        int $addressBookId,
        int $userId,
        array $duplicateDecisions = []
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
        [$importedContacts, $mergedContacts, $skippedDuplicates] =
            $this->transactions->transactional(
                function () use (
                    $candidates,
                    $duplicateDecisions,
                    $addressBookId
                ): array {
                    $imported = 0;
                    $merged = 0;
                    $skipped = 0;

                    foreach ($candidates as $candidate) {
                        $decision = $duplicateDecisions[
                            $candidate->sourceRow
                        ] ?? new ImportDuplicateDecision(
                            ImportDuplicateAction::Skip
                        );
                        $action = $decision->action;

                        if (
                            $action === ImportDuplicateAction::Merge
                            && (
                                $candidate->duplicateStatus
                                    !== ImportDuplicateStatus::ExistingContact
                                || $candidate->mergeTarget === null
                                || $decision->mergeTargetContactId
                                    !== $candidate->mergeTarget->id
                            )
                        ) {
                            throw new RuntimeException(
                                'Import merge target is no longer unambiguous.'
                            );
                        }

                        if (!$candidate->duplicateStatus->isDuplicate()) {
                            $action = ImportDuplicateAction::Import;
                        }

                        if ($action === ImportDuplicateAction::Skip) {
                            $skipped++;
                            continue;
                        }

                        if ($action === ImportDuplicateAction::Merge) {
                            $mergedValues = $this->merger->merge(
                                $candidate->mergeTarget,
                                $candidate->contact
                            );

                            if (
                                !$this->contacts->update(
                                    $candidate->mergeTarget->id,
                                    $addressBookId,
                                    $mergedValues['display_name'],
                                    $mergedValues['given_name'],
                                    $mergedValues['family_name'],
                                    $mergedValues['organization_name'],
                                    $mergedValues['notes'],
                                    $mergedValues['phone_numbers'],
                                )
                            ) {
                                throw new RuntimeException(
                                    'Import merge target could not be updated.'
                                );
                            }

                            $merged++;
                            continue;
                        }

                        if ($action !== ImportDuplicateAction::Import) {
                            throw new RuntimeException(
                                'Invalid duplicate import action.'
                            );
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
                        $imported++;
                    }

                    return [$imported, $merged, $skipped];
                }
            );

        $this->drafts->discard($token);

        return new ContactImportResult(
            $importedContacts,
            $mergedContacts,
            $skippedDuplicates
        );
    }
}
