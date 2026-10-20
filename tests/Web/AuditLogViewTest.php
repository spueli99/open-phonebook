<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Web;

use DateTimeImmutable;
use OpenPhonebook\Audit\AuditEvent;
use OpenPhonebook\Audit\AuditEventType;
use OpenPhonebook\Audit\AuditTargetType;
use OpenPhonebook\I18n\Translator;
use OpenPhonebook\Web\AuditLogView;
use PHPUnit\Framework\TestCase;

final class AuditLogViewTest extends TestCase
{
    public function testEscapesAuditMetadataAndFormatsTimezone(): void
    {
        $html = AuditLogView::render(
            Translator::load(dirname(__DIR__, 2), 'en'),
            [
                new AuditEvent(
                    1,
                    AuditEventType::InvitationRevoked,
                    7,
                    AuditTargetType::Invitation,
                    9,
                    ['reason' => '<script>alert(1)</script>'],
                    new DateTimeImmutable('2026-10-20 12:00:00 UTC')
                ),
            ],
            'Europe/Berlin'
        );

        self::assertStringNotContainsString(
            '<script>alert(1)</script>',
            $html
        );
        self::assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $html
        );
        self::assertStringContainsString(
            '2026-10-20 14:00:00 CEST',
            $html
        );
        self::assertStringContainsString('#7', $html);
        self::assertStringContainsString(
            'invitation.revoked',
            $html
        );
    }

    public function testShowsDeletedActorAndEmptyState(): void
    {
        $translator = Translator::load(dirname(__DIR__, 2), 'en');

        $empty = AuditLogView::render($translator, [], 'UTC');
        self::assertStringContainsString(
            'There are no audit events yet.',
            $empty
        );

        $withDeletedActor = AuditLogView::render(
            $translator,
            [
                new AuditEvent(
                    2,
                    AuditEventType::UserDeactivated,
                    null,
                    AuditTargetType::User,
                    3,
                    [],
                    new DateTimeImmutable('2026-10-20 12:00:00 UTC')
                ),
            ],
            'UTC'
        );

        self::assertStringContainsString(
            'Deleted user',
            $withDeletedActor
        );
    }
}
