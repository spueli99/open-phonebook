<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use OpenPhonebook\Clock\ClockInterface;
use RuntimeException;

final class QuietHoursPolicy
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly UserTimezoneRepositoryInterface $timezones,
    ) {
    }

    public function isQuiet(NotificationRule $rule): bool
    {
        if ($rule->quietStart === null || $rule->quietEnd === null) {
            return false;
        }

        try {
            $timezone = new DateTimeZone(
                $this->timezones->timezoneForUser($rule->ownerUserId)
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'User timezone is invalid.',
                0,
                $exception
            );
        }

        $local = $this->clock->now()->setTimezone($timezone);
        $current = $local->format('H:i');

        if ($rule->quietStart < $rule->quietEnd) {
            return $current >= $rule->quietStart
                && $current < $rule->quietEnd;
        }

        return $current >= $rule->quietStart
            || $current < $rule->quietEnd;
    }

    public static function validateWindow(
        ?string $start,
        ?string $end
    ): void {
        if ($start === null && $end === null) {
            return;
        }

        if (
            $start === null
            || $end === null
            || !self::validTime($start)
            || !self::validTime($end)
            || $start === $end
        ) {
            throw new RuntimeException('Invalid quiet-hours window.');
        }
    }

    private static function validTime(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!H:i', $value);

        return $date instanceof DateTimeImmutable
            && $date->format('H:i') === $value;
    }
}
