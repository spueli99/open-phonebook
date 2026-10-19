<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

final readonly class GoogleContactsSyncResult
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $deleted,
        public int $skipped,
    ) {
    }
}
