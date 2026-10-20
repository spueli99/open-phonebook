<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Notifications;

use OpenPhonebook\Notifications\NotificationRule;
use OpenPhonebook\Notifications\NotificationTrigger;
use OpenPhonebook\Notifications\QuietHoursPolicy;
use OpenPhonebook\Tests\Support\Clock\FrozenClock;
use OpenPhonebook\Tests\Support\Notifications\InMemoryUserTimezoneRepository;
use PHPUnit\Framework\TestCase;

final class QuietHoursPolicyTest extends TestCase
{
    public function testOvernightWindowUsesUserTimezone(): void
    {
        $clock = new FrozenClock('2026-10-20 21:30:00');
        $timezones = new InMemoryUserTimezoneRepository();
        $timezones->set(10, 'Europe/Berlin');
        $policy = new QuietHoursPolicy($clock, $timezones);
        $rule = new NotificationRule(
            1,
            10,
            1,
            null,
            NotificationTrigger::Incoming,
            '22:00',
            '07:00',
            true
        );

        self::assertTrue($policy->isQuiet($rule));
    }

    public function testDaytimeWindowEndsExclusively(): void
    {
        $clock = new FrozenClock('2026-10-20 15:00:00');
        $timezones = new InMemoryUserTimezoneRepository();
        $timezones->set(10, 'UTC');
        $policy = new QuietHoursPolicy($clock, $timezones);
        $rule = new NotificationRule(
            1,
            10,
            1,
            null,
            NotificationTrigger::Incoming,
            '13:00',
            '15:00',
            true
        );

        self::assertFalse($policy->isQuiet($rule));
    }

    public function testNoWindowIsNeverQuiet(): void
    {
        $clock = new FrozenClock('2026-10-20 23:00:00');
        $timezones = new InMemoryUserTimezoneRepository();
        $timezones->set(10, 'UTC');
        $policy = new QuietHoursPolicy($clock, $timezones);
        $rule = new NotificationRule(
            1,
            10,
            1,
            null,
            NotificationTrigger::Incoming,
            null,
            null,
            true
        );

        self::assertFalse($policy->isQuiet($rule));
    }
}
