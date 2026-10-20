<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Notifications;

use OpenPhonebook\Calls\CallDirection;
use OpenPhonebook\Calls\CallEventResult;
use OpenPhonebook\Calls\CallSession;
use OpenPhonebook\Calls\CallSessionState;
use OpenPhonebook\Devices\AuthenticatedDevice;
use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Notifications\CallNotificationDispatcher;
use OpenPhonebook\Notifications\NotificationTrigger;
use OpenPhonebook\Notifications\QuietHoursPolicy;
use OpenPhonebook\Tests\Support\Clock\FrozenClock;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationChannelRepository;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationDeliveryRepository;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationRuleRepository;
use OpenPhonebook\Tests\Support\Notifications\InMemoryUserTimezoneRepository;
use PHPUnit\Framework\TestCase;

final class CallNotificationDispatcherTest extends TestCase
{
    public function testIncomingRuleQueuesResolvedCaller(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $rules = new InMemoryNotificationRuleRepository();
        $deliveries = new InMemoryNotificationDeliveryRepository();
        $channelId = $channels->create(
            10,
            'Push',
            'pushover',
            'encrypted'
        );
        $rules->create(
            10,
            $channelId,
            1,
            NotificationTrigger::Incoming,
            null,
            null
        );
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $dispatcher = new CallNotificationDispatcher(
            $rules,
            $channels,
            $deliveries,
            $this->quietHours($clock),
            $clock
        );

        $dispatcher->dispatch(
            $this->device(),
            new CallEventResult(
                new CallSession(
                    1,
                    1,
                    'call-1',
                    CallDirection::Incoming,
                    CallSessionState::Ringing,
                    '100',
                    '+49123',
                    'Alice'
                ),
                true
            )
        );

        $counts = $deliveries->countsOwnedBy(10);
        self::assertSame(1, $counts['pending']);
        self::assertSame(
            'Incoming call from Alice',
            $deliveries->get(1)->body
        );
    }

    public function testQuietHoursSuppressNotification(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $rules = new InMemoryNotificationRuleRepository();
        $deliveries = new InMemoryNotificationDeliveryRepository();
        $channelId = $channels->create(
            10,
            'Push',
            'pushover',
            'encrypted'
        );
        $rules->create(
            10,
            $channelId,
            null,
            NotificationTrigger::Missed,
            '22:00',
            '07:00'
        );
        $clock = new FrozenClock('2026-10-20 23:30:00');
        $dispatcher = new CallNotificationDispatcher(
            $rules,
            $channels,
            $deliveries,
            $this->quietHours($clock),
            $clock
        );

        $dispatcher->dispatch(
            $this->device(),
            new CallEventResult(
                new CallSession(
                    2,
                    1,
                    'call-2',
                    CallDirection::Incoming,
                    CallSessionState::Missed,
                    '100',
                    '+49123',
                    null
                ),
                false
            )
        );

        self::assertSame(
            0,
            $deliveries->countsOwnedBy(10)['pending']
        );
    }

    public function testInactiveChannelDoesNotQueue(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $rules = new InMemoryNotificationRuleRepository();
        $deliveries = new InMemoryNotificationDeliveryRepository();
        $channelId = $channels->create(
            10,
            'Disabled',
            'pushover',
            'encrypted'
        );
        $channels->setActive($channelId, 10, false);
        $rules->create(
            10,
            $channelId,
            null,
            NotificationTrigger::Completed,
            null,
            null
        );
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $dispatcher = new CallNotificationDispatcher(
            $rules,
            $channels,
            $deliveries,
            $this->quietHours($clock),
            $clock
        );

        $dispatcher->dispatch(
            $this->device(),
            new CallEventResult(
                new CallSession(
                    3,
                    1,
                    'call-3',
                    CallDirection::Incoming,
                    CallSessionState::Completed,
                    '100',
                    '+49123',
                    null
                ),
                false
            )
        );

        self::assertSame(
            0,
            $deliveries->countsOwnedBy(10)['pending']
        );
    }

    private function quietHours(FrozenClock $clock): QuietHoursPolicy
    {
        $timezones = new InMemoryUserTimezoneRepository();
        $timezones->set(10, 'UTC');

        return new QuietHoursPolicy($clock, $timezones);
    }

    private function device(): AuthenticatedDevice
    {
        return new AuthenticatedDevice(
            1,
            10,
            DeviceCredentialScope::CallEventsWrite
        );
    }
}
