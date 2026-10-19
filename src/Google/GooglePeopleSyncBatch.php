<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

final readonly class GooglePeopleSyncBatch
{
    /**
     * @param list<GooglePersonSnapshot> $people
     */
    public function __construct(
        public array $people,
        public string $nextSyncToken,
        public bool $fullSnapshot,
    ) {
    }
}
