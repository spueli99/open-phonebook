<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

final readonly class GoogleContactsSchedulerResult
{
    /**
     * @param list<int> $failedSourceIds
     */
    public function __construct(
        public int $attempted,
        public int $succeeded,
        public array $failedSourceIds,
    ) {
    }

    public function failed(): int
    {
        return count($this->failedSourceIds);
    }
}
