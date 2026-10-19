<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\ContactPhoneNumberInput;
use OpenPhonebook\Contacts\ContactRepositoryInterface;
use OpenPhonebook\Contacts\PhoneNumberNormalizerInterface;
use OpenPhonebook\Database\TransactionManagerInterface;
use OpenPhonebook\Security\SecretCipher;
use OpenPhonebook\Sources\ContactSource;
use OpenPhonebook\Sources\ContactSourceRepositoryInterface;
use RuntimeException;

final class GoogleContactsSyncService implements GoogleContactSourceSynchronizerInterface
{
    public function __construct(
        private readonly AddressBookAccessService $access,
        private readonly ContactSourceRepositoryInterface $sources,
        private readonly ContactRepositoryInterface $contacts,
        private readonly PhoneNumberNormalizerInterface $normalizer,
        private readonly SecretCipher $cipher,
        private readonly GoogleOAuthClient $oauth,
        private readonly GooglePeopleClient $people,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function sync(
        int $ownerUserId,
        int $addressBookId
    ): GoogleContactsSyncResult {
        $this->access->requireOwner($addressBookId, $ownerUserId);

        $source = $this->sources->findGoogleForAddressBook(
            $ownerUserId,
            $addressBookId
        );

        if ($source === null) {
            throw new RuntimeException(
                'Google contact source is not connected.'
            );
        }

        return $this->syncSource($source);
    }

    public function syncSource(
        ContactSource $source
    ): GoogleContactsSyncResult {
        $refreshToken = $this->cipher->decrypt(
            $source->encryptedRefreshToken
        );
        $tokenSet = $this->oauth->refresh($refreshToken);
        $batch = $this->loadBatch(
            $source,
            $tokenSet->accessToken
        );

        return $this->transactions->transactional(
            function () use (
                $source,
                $batch
            ): GoogleContactsSyncResult {
                $records = [];

                foreach (
                    $this->sources->recordsForSource($source->id) as $record
                ) {
                    $records[$record->externalId] = $record->contactId;
                }

                $seen = [];
                $created = 0;
                $updated = 0;
                $deleted = 0;
                $skipped = 0;

                foreach ($batch->people as $person) {
                    if (isset($seen[$person->externalId])) {
                        throw new RuntimeException(
                            'Google sync contains duplicate resource IDs.'
                        );
                    }

                    $seen[$person->externalId] = true;
                    $contactId = $records[$person->externalId] ?? null;
                    $previousRecordId = null;

                    if ($contactId === null) {
                        foreach (
                            $person->previousExternalIds as $previousExternalId
                        ) {
                            if (isset($records[$previousExternalId])) {
                                $contactId = $records[$previousExternalId];
                                $previousRecordId = $previousExternalId;
                                break;
                            }
                        }
                    }

                    if ($person->deleted) {
                        if ($contactId !== null) {
                            $this->contacts->softDelete(
                                $contactId,
                                $source->addressBookId
                            );
                            $this->deleteSourceMappings(
                                $source->id,
                                $person->externalId,
                                $person->previousExternalIds,
                                $records,
                                $contactId
                            );
                            $deleted++;
                        }

                        continue;
                    }

                    if ($person->contact === null) {
                        if (
                            $contactId !== null
                            && $previousRecordId !== null
                        ) {
                            $this->moveSourceMapping(
                                $source->id,
                                $person->externalId,
                                $person->previousExternalIds,
                                $records,
                                $contactId
                            );
                        }

                        $skipped++;
                        continue;
                    }

                    $phoneNumbers = [];

                    foreach (
                        $person->contact->phoneNumbers as $phoneNumber
                    ) {
                        $phoneNumbers[] = new ContactPhoneNumberInput(
                            $phoneNumber->label,
                            $phoneNumber->rawNumber,
                            $this->normalizer->normalize(
                                $phoneNumber->rawNumber
                            ),
                        );
                    }

                    if ($contactId !== null) {
                        $didUpdate = $this->contacts->update(
                            $contactId,
                            $source->addressBookId,
                            $person->contact->displayName,
                            $person->contact->givenName,
                            $person->contact->familyName,
                            $person->contact->organizationName,
                            $person->contact->notes,
                            $phoneNumbers,
                        );

                        if ($didUpdate) {
                            $this->moveSourceMapping(
                                $source->id,
                                $person->externalId,
                                $person->previousExternalIds,
                                $records,
                                $contactId
                            );
                            $updated++;
                            continue;
                        }
                    }

                    $contactId = $this->contacts->create(
                        $source->addressBookId,
                        $person->contact->displayName,
                        $person->contact->givenName,
                        $person->contact->familyName,
                        $person->contact->organizationName,
                        $person->contact->notes,
                        $phoneNumbers,
                    );
                    $this->sources->upsertRecord(
                        $source->id,
                        $person->externalId,
                        $contactId
                    );
                    $created++;
                }

                if ($batch->fullSnapshot) {
                    foreach ($records as $externalId => $contactId) {
                        if (isset($seen[$externalId])) {
                            continue;
                        }

                        $this->contacts->softDelete(
                            $contactId,
                            $source->addressBookId
                        );
                        $this->sources->deleteRecord(
                            $source->id,
                            $externalId
                        );
                        $deleted++;
                    }
                }

                $this->sources->markSynced(
                    $source->id,
                    $this->cipher->encrypt($batch->nextSyncToken)
                );

                return new GoogleContactsSyncResult(
                    $created,
                    $updated,
                    $deleted,
                    $skipped,
                );
            }
        );
    }

    private function loadBatch(
        ContactSource $source,
        string $accessToken
    ): GooglePeopleSyncBatch {
        if ($source->encryptedSyncToken === null) {
            return $this->people->fullSnapshot($accessToken);
        }

        $syncToken = $this->cipher->decrypt(
            $source->encryptedSyncToken
        );

        try {
            return $this->people->changesSince(
                $accessToken,
                $syncToken
            );
        } catch (GoogleSyncTokenExpired) {
            return $this->people->fullSnapshot($accessToken);
        }
    }

    /**
     * @param list<string> $previousExternalIds
     * @param array<string, int> $records
     */
    private function deleteSourceMappings(
        int $sourceId,
        string $externalId,
        array $previousExternalIds,
        array $records,
        int $contactId
    ): void {
        if (($records[$externalId] ?? null) === $contactId) {
            $this->sources->deleteRecord($sourceId, $externalId);
        }

        foreach ($previousExternalIds as $previousExternalId) {
            if (
                ($records[$previousExternalId] ?? null)
                === $contactId
            ) {
                $this->sources->deleteRecord(
                    $sourceId,
                    $previousExternalId
                );
            }
        }
    }

    /**
     * @param list<string> $previousExternalIds
     * @param array<string, int> $records
     */
    private function moveSourceMapping(
        int $sourceId,
        string $externalId,
        array $previousExternalIds,
        array $records,
        int $contactId
    ): void {
        foreach ($previousExternalIds as $previousExternalId) {
            if (
                $previousExternalId !== $externalId
                && ($records[$previousExternalId] ?? null)
                    === $contactId
            ) {
                $this->sources->deleteRecord(
                    $sourceId,
                    $previousExternalId
                );
            }
        }

        $this->sources->upsertRecord(
            $sourceId,
            $externalId,
            $contactId
        );
    }
}
