<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Web;

use DateTimeImmutable;
use OpenPhonebook\I18n\Translator;
use OpenPhonebook\Notifications\NotificationChannel;
use OpenPhonebook\Notifications\NotificationDeliveryHistoryFilter;
use OpenPhonebook\Notifications\NotificationDeliveryHistoryItem;
use OpenPhonebook\Notifications\NotificationDeliveryHistoryPage;
use OpenPhonebook\Notifications\NotificationDeliveryStatus;
use OpenPhonebook\Notifications\NotificationTrigger;
use OpenPhonebook\Web\NotificationManagementView;
use PHPUnit\Framework\TestCase;

final class NotificationManagementViewTest extends TestCase
{
    public function testHistoryFiltersAndOlderLinkAreRenderedSafely(): void
    {
        $channel = new NotificationChannel(
            4,
            10,
            '<Mail>',
            'email',
            'encrypted',
            true
        );
        $filter = new NotificationDeliveryHistoryFilter(
            NotificationDeliveryStatus::Failed,
            NotificationTrigger::Test,
            4
        );
        $page = new NotificationDeliveryHistoryPage(
            [
                new NotificationDeliveryHistoryItem(
                    11,
                    4,
                    '<Mail>',
                    NotificationTrigger::Test,
                    NotificationDeliveryStatus::Failed,
                    2,
                    new DateTimeImmutable('2026-10-20 10:00:00 UTC'),
                    new DateTimeImmutable('2026-10-20 10:01:00 UTC'),
                    null,
                    new DateTimeImmutable('2026-10-20 09:59:00 UTC'),
                    true
                ),
            ],
            11
        );

        $html = NotificationManagementView::render(
            Translator::load(dirname(__DIR__, 2), 'en'),
            [$channel],
            [],
            [],
            [
                'pending' => 0,
                'processing' => 0,
                'sent' => 0,
                'failed' => 1,
                'cancelled' => 0,
            ],
            $page,
            $filter,
            'Europe/Berlin',
            'csrf'
        );

        self::assertStringContainsString(
            '&lt;Mail&gt;',
            $html
        );
        self::assertStringNotContainsString(
            '<Mail>',
            $html
        );
        self::assertStringContainsString(
            'status=failed&amp;trigger=test&amp;channel=4&amp;before=11',
            $html
        );
        self::assertStringContainsString(
            '<option value="failed" selected>',
            $html
        );
        self::assertStringContainsString(
            '<option value="test" selected>',
            $html
        );
        self::assertStringContainsString(
            '<option value="4" selected>',
            $html
        );
    }
}
