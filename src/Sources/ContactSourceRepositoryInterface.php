<?php

declare(strict_types=1);

namespace OpenPhonebook\Sources;

interface ContactSourceRepositoryInterface
{
    public function findGoogleForAddressBook(
        int $ownerUserId,
        int $addressBookId
    ): ?ContactSource;

    /**
     * @return list<ContactSource>
     */
    public function allGoogleSources(): array;

    public function upsertGoogleSource(
        int $ownerUserId,
        int $addressBookId,
        string $encryptedRefreshToken
    ): ContactSource;

    /**
     * @return list<SourceRecord>
     */
    public function recordsForSource(int $sourceId): array;

    public function upsertRecord(
        int $sourceId,
        string $externalId,
        int $contactId
    ): void;

    public function deleteRecord(
        int $sourceId,
        string $externalId
    ): void;

    public function markSynced(
        int $sourceId,
        ?string $encryptedSyncToken
    ): void;
}
