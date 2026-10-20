<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Web;

use DateTimeImmutable;
use OpenPhonebook\Devices\Device;
use OpenPhonebook\I18n\Translator;
use OpenPhonebook\Web\DeviceManagementView;
use PHPUnit\Framework\TestCase;

final class DeviceManagementViewTest extends TestCase
{
    public function testRendersCredentialUsageStatesInUserTimezone(): void
    {
        $html = DeviceManagementView::render(
            Translator::load(dirname(__DIR__, 2), 'en'),
            [
                new Device(
                    1,
                    10,
                    'Desk phone',
                    'Yealink',
                    'T54W',
                    true
                ),
            ],
            [],
            [1 => []],
            [1 => true],
            [1 => true],
            [
                1 => new DateTimeImmutable(
                    '2026-10-20 12:00:00 UTC'
                ),
            ],
            [1 => null],
            'Europe/Berlin',
            'csrf'
        );

        self::assertStringContainsString(
            'Last used: 2026-10-20 14:00:00 CEST',
            $html
        );
        self::assertStringContainsString(
            'Last used: never',
            $html
        );
    }

    public function testShowsNoActiveCredentialState(): void
    {
        $html = DeviceManagementView::render(
            Translator::load(dirname(__DIR__, 2), 'en'),
            [
                new Device(
                    1,
                    10,
                    'Desk phone',
                    null,
                    null,
                    true
                ),
            ],
            [],
            [1 => []],
            [1 => false],
            [1 => false],
            [1 => null],
            [1 => null],
            'UTC',
            'csrf'
        );

        self::assertStringContainsString(
            'No active credential',
            $html
        );
    }
}
