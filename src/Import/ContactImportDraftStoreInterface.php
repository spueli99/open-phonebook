<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

interface ContactImportDraftStoreInterface
{
    public function create(
        int $addressBookId,
        int $userId,
        string $contents
    ): string;

    public function resolve(
        string $token,
        int $addressBookId,
        int $userId
    ): ?ContactImportDraft;

    public function discard(string $token): void;
}
