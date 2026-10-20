<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Web;

use DateTimeImmutable;
use OpenPhonebook\Calls\CallDirection;
use OpenPhonebook\Calls\CallHistoryItem;
use OpenPhonebook\Calls\CallHistoryPage;
use OpenPhonebook\Calls\CallSessionState;
use OpenPhonebook\I18n\Translator;
use OpenPhonebook\Web\CallHistoryView;
use PHPUnit\Framework\TestCase;

final class CallHistoryViewTest extends TestCase
{
    public function testRendersAndEscapesCallHistory(): void
    {
        $html = CallHistoryView::render(
            Translator::load(dirname(__DIR__, 2), 'en'),
            new CallHistoryPage(
                [
                    new CallHistoryItem(
                        10,
                        'Desk <Phone>',
                        CallDirection::Incoming,
                        CallSessionState::Missed,
                        '+491701234567',
                        '<script>alert(1)</script>',
                        new DateTimeImmutable(
                            '2026-10-20 12:00:00 UTC'
                        ),
                        new DateTimeImmutable(
                            '2026-10-20 12:00:10 UTC'
                        )
                    ),
                ],
                10
            ),
            'Europe/Berlin'
        );

        self::assertStringContainsString('Incoming', $html);
        self::assertStringContainsString('Missed', $html);
        self::assertStringContainsString(
            '2026-10-20 14:00:00 CEST',
            $html
        );
        self::assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $html
        );
        self::assertStringNotContainsString(
            '<script>alert(1)</script>',
            $html
        );
        self::assertStringContainsString(
            '/calls/?before=10',
            $html
        );
    }

    public function testRendersEmptyState(): void
    {
        $html = CallHistoryView::render(
            Translator::load(dirname(__DIR__, 2), 'en'),
            new CallHistoryPage([], null),
            'UTC'
        );

        self::assertStringContainsString(
            'There are no calls yet.',
            $html
        );
    }
}
