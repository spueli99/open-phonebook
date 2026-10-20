<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Devices;

use DateInterval;
use OpenPhonebook\Devices\DeviceRequestThrottle;
use OpenPhonebook\Tests\Support\Clock\FrozenClock;
use OpenPhonebook\Tests\Support\Devices\InMemoryDeviceRequestAttemptRepository;
use PHPUnit\Framework\TestCase;

final class DeviceRequestThrottleTest extends TestCase
{
    private const APP_KEY =
        '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testTwentyFailuresBlockEndpointAndIp(): void
    {
        $repository = new InMemoryDeviceRequestAttemptRepository();
        $clock = new FrozenClock('2026-10-20 05:00:00');
        $throttle = new DeviceRequestThrottle(
            $repository,
            self::APP_KEY,
            $clock
        );

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $throttle->registerFailure(
                'phonebook',
                '192.0.2.10'
            );
        }

        self::assertTrue(
            $throttle->isBlocked(
                'phonebook',
                '192.0.2.10'
            )
        );
    }

    public function testEndpointsHaveIndependentBuckets(): void
    {
        $repository = new InMemoryDeviceRequestAttemptRepository();
        $clock = new FrozenClock('2026-10-20 05:00:00');
        $throttle = new DeviceRequestThrottle(
            $repository,
            self::APP_KEY,
            $clock
        );

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $throttle->registerFailure(
                'phonebook',
                '192.0.2.10'
            );
        }

        self::assertFalse(
            $throttle->isBlocked(
                'call-events',
                '192.0.2.10'
            )
        );
    }

    public function testWindowResetsAfterFifteenMinutes(): void
    {
        $repository = new InMemoryDeviceRequestAttemptRepository();
        $clock = new FrozenClock('2026-10-20 05:00:00');
        $throttle = new DeviceRequestThrottle(
            $repository,
            self::APP_KEY,
            $clock
        );

        for ($attempt = 0; $attempt < 19; $attempt++) {
            $throttle->registerFailure(
                'phonebook',
                '192.0.2.10'
            );
        }

        $clock->advance(new DateInterval('PT16M'));
        $throttle->registerFailure(
            'phonebook',
            '192.0.2.10'
        );

        self::assertFalse(
            $throttle->isBlocked(
                'phonebook',
                '192.0.2.10'
            )
        );
    }

    public function testClearRemovesFailureBucket(): void
    {
        $repository = new InMemoryDeviceRequestAttemptRepository();
        $clock = new FrozenClock('2026-10-20 05:00:00');
        $throttle = new DeviceRequestThrottle(
            $repository,
            self::APP_KEY,
            $clock
        );

        $throttle->registerFailure(
            'yealink-action-url',
            '192.0.2.10'
        );
        self::assertNotEmpty($repository->all());

        $throttle->clear(
            'yealink-action-url',
            '192.0.2.10'
        );

        self::assertSame([], $repository->all());
    }
}
