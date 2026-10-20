<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Web;

use OpenPhonebook\Auth\AuthenticatedUser;
use OpenPhonebook\I18n\Translator;
use OpenPhonebook\Web\DashboardView;
use PHPUnit\Framework\TestCase;

final class DashboardViewTest extends TestCase
{
    public function testRendersNavigationCardsAccordingToPermissions(): void
    {
        $html = DashboardView::render(
            Translator::load(dirname(__DIR__, 2), 'en'),
            new AuthenticatedUser(
                1,
                'admin@example.test',
                'Admin User',
                'system_admin',
                'en',
                'Europe/Berlin',
                1
            ),
            'csrf',
            true,
            true,
            true
        );

        self::assertStringContainsString(
            'class="dashboard-grid"',
            $html
        );
        self::assertStringContainsString(
            'class="dashboard-card"',
            $html
        );
        self::assertStringContainsString('/address-books/', $html);
        self::assertStringContainsString('/admin/operations/', $html);
        self::assertStringContainsString('/admin/audit/', $html);
        self::assertStringContainsString('/admin/users/', $html);
    }

    public function testOmitsAdministrativeCardsWithoutPermissions(): void
    {
        $html = DashboardView::render(
            Translator::load(dirname(__DIR__, 2), 'en'),
            new AuthenticatedUser(
                2,
                'member@example.test',
                'Member User',
                'member',
                'en',
                1
            ),
            'csrf',
            false,
            false,
            false
        );

        self::assertStringNotContainsString('/admin/operations/', $html);
        self::assertStringNotContainsString('/admin/audit/', $html);
        self::assertStringNotContainsString('/admin/users/', $html);
    }
}
