<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\ContactPhoneNumberInput;
use OpenPhonebook\Contacts\ContactRepositoryInterface;
use OpenPhonebook\Contacts\PhoneNumberNormalizerInterface;
use OpenPhonebook\Database\TransactionManagerInterface;
use OpenPhonebook\Security\SecretCipher;
use OpenPhonebook\Sources\ContactSourceRepositoryInterface;
use RuntimeException;

final class GoogleContactsSyncService
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

        $refreshToken = $this->cipher->decrypt(
            $source->encryptedRefreshToken
        );
        $tokenSet = $this->oauth->refresh($refreshToken);
        $snapshot = $this->people->allConnections(
            $tokenSet->accessToken
        );

        return $this->transactions->transactional(
            function () use (
                $source,
                $snapshot,
                $addressBookId
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

                foreach ($snapshot as $person) {
                    if (isset($seen[$person->externalId])) {
                        throw new RuntimeException(
                            'Google snapshot contains duplicate resource IDs.'
                        );
                    }

                    $seen[$person->externalId] = true;

                    if ($person->contact === null) {
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

                    $contactId = $records[$person->externalId] ?? null;

                    if ($contactId !== null) {
                        $didUpdate = $this->contacts->update(
                            $contactId,
                            $addressBookId,
                            $person->contact->displayName,
                            $person->contact->givenName,
                            $person->contact->familyName,
                            $person->contact->organizationName,
                            $person->contact->notes,
                            $phoneNumbers,
                        );

                        if ($didUpdate) {
                            $updated++;
                            continue;
                        }
                    }

                    $contactId = $this->contacts->create(
                        $addressBookId,
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

                foreach ($records as $externalId => $contactId) {
                    if (isset($seen[$externalId])) {
                        continue;
                    }

                    $this->contacts->softDelete(
                        $contactId,
                        $addressBookId
                    );
                    $this->sources->deleteRecord(
                        $source->id,
                        $externalId
                    );
                    $deleted++;
                }

                $this->sources->markSynced($source->id);

                return new GoogleContactsSyncResult(
                    $created,
                    $updated,
                    $deleted,
                    $skipped,
                );
            }
        );
    }
}
