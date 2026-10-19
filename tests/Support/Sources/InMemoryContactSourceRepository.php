<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Sources;

use OpenPhonebook\Sources\ContactSource;
use OpenPhonebook\Sources\ContactSourceRepositoryInterface;
use OpenPhonebook\Sources\SourceRecord;

final class InMemoryContactSourceRepository implements ContactSourceRepositoryInterface
{
    private ?ContactSource $source = null;

    /**
     * @var list<ContactSource>
     */
    private array $additionalSources = [];

    /**
     * @var array<string, int>
     */
    private array $records = [];

    public int $markSyncedCalls = 0;

    public function findGoogleForAddressBook(
        int $ownerUserId,
        int $addressBookId
    ): ?ContactSource {
        if (
            $this->source === null
            || $this->source->ownerUserId !== $ownerUserId
            || $this->source->addressBookId !== $addressBookId
        ) {
            return null;
        }

        return $this->source;
    }

    public function allGoogleSources(): array
    {
        $sources = $this->source === null ? [] : [$this->source];

        return array_merge($sources, $this->additionalSources);
    }

    public function addSource(ContactSource $source): void
    {
        $this->additionalSources[] = $source;
    }

    public function upsertGoogleSource(
        int $ownerUserId,
        int $addressBookId,
        string $encryptedRefreshToken
    ): ContactSource {
        $this->source = new ContactSource(
            1,
            $ownerUserId,
            $addressBookId,
            'google',
            $encryptedRefreshToken,
            null
        );

        return $this->source;
    }

    public function recordsForSource(int $sourceId): array
    {
        $result = [];

        foreach ($this->records as $externalId => $contactId) {
            $result[] = new SourceRecord($externalId, $contactId);
        }

        return $result;
    }

    public function upsertRecord(
        int $sourceId,
        string $externalId,
        int $contactId
    ): void {
        $this->records[$externalId] = $contactId;
    }

    public function deleteRecord(
        int $sourceId,
        string $externalId
    ): void {
        unset($this->records[$externalId]);
    }

    public function markSynced(
        int $sourceId,
        ?string $encryptedSyncToken
    ): void {
        $this->markSyncedCalls++;

        if ($this->source !== null) {
            $this->source = new ContactSource(
                $this->source->id,
                $this->source->ownerUserId,
                $this->source->addressBookId,
                $this->source->provider,
                $this->source->encryptedRefreshToken,
                $encryptedSyncToken
            );
        }
    }
}
