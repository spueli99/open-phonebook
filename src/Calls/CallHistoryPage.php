<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

final readonly class CallHistoryPage
{
    /**
     * @param list<CallHistoryItem> $items
     */
    public function __construct(
        public array $items,
        public ?int $olderBeforeId,
    ) {
    }
}
