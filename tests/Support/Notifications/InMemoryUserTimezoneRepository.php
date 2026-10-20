<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Notifications;

use OpenPhonebook\Notifications\UserTimezoneRepositoryInterface;
use RuntimeException;

final class InMemoryUserTimezoneRepository implements UserTimezoneRepositoryInterface
{
    /** @var array<int, string> */
    private array $timezones = [];

    public function set(int $userId, string $timezone): void
    {
        $this->timezones[$userId] = $timezone;
    }

    public function timezoneForUser(int $userId): string
    {
        return $this->timezones[$userId]
            ?? throw new RuntimeException('Timezone not found.');
    }
}
