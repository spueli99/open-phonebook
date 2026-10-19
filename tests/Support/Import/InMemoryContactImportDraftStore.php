<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Import;

use DateTimeImmutable;
use OpenPhonebook\Import\ContactImportDraft;
use OpenPhonebook\Import\ContactImportDraftStoreInterface;

final class InMemoryContactImportDraftStore implements ContactImportDraftStoreInterface
{
    /**
     * @var array<string, ContactImportDraft>
     */
    private array $drafts = [];

    private int $nextToken = 1;

    public function create(
        int $addressBookId,
        int $userId,
        string $contents
    ): string {
        $token = 'draft-' . $this->nextToken++;
        $this->drafts[$token] = new ContactImportDraft(
            $addressBookId,
            $userId,
            $contents,
            new DateTimeImmutable('+15 minutes'),
        );

        return $token;
    }

    public function resolve(
        string $token,
        int $addressBookId,
        int $userId
    ): ?ContactImportDraft {
        $draft = $this->drafts[$token] ?? null;

        if (
            $draft === null
            || $draft->addressBookId !== $addressBookId
            || $draft->userId !== $userId
        ) {
            return null;
        }

        return $draft;
    }

    public function discard(string $token): void
    {
        unset($this->drafts[$token]);
    }
}
