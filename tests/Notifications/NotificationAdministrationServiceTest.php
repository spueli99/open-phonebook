<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Notifications;

use OpenPhonebook\Notifications\NotificationAdministrationService;
use OpenPhonebook\Notifications\NotificationConfigCipher;
use OpenPhonebook\Notifications\NotificationDeliveryHistoryFilter;
use OpenPhonebook\Notifications\NotificationDeliveryStatus;
use OpenPhonebook\Notifications\NotificationMessageCipher;
use OpenPhonebook\Notifications\NotificationTrigger;
use OpenPhonebook\Security\SecretCipher;
use OpenPhonebook\Tests\Support\Clock\FrozenClock;
use OpenPhonebook\Tests\Support\Devices\InMemoryDeviceRepository;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationChannelRepository;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationDeliveryRepository;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationRuleRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NotificationAdministrationServiceTest extends TestCase
{
    private const APP_KEY =
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testChannelConfigIsEncryptedAndRuleRequiresOwnedDevice(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $rules = new InMemoryNotificationRuleRepository();
        $devices = new InMemoryDeviceRepository();
        $devices->create(10, 'Phone', null, null);
        $cipher = new NotificationConfigCipher(
            new SecretCipher(self::APP_KEY)
        );
        $service = new NotificationAdministrationService(
            $channels,
            $rules,
            $devices,
            $cipher,
            new InMemoryNotificationDeliveryRepository(),
            NotificationMessageCipher::fromApplicationKey(
                self::APP_KEY
            ),
            new FrozenClock('2026-10-20 10:00:00')
        );

        $channelId = $service->createChannel(
            10,
            'Push',
            'pushover',
            [
                'token' => str_repeat('A', 30),
                'user' => str_repeat('B', 30),
            ]
        );
        $channel = $channels->findOwnedBy($channelId, 10);

        self::assertNotNull($channel);
        self::assertStringNotContainsString(
            str_repeat('A', 30),
            $channel->encryptedConfig
        );
        self::assertSame(
            [
                'token' => str_repeat('A', 30),
                'user' => str_repeat('B', 30),
            ],
            $cipher->decrypt($channel->encryptedConfig)
        );

        $service->createRule(
            10,
            $channelId,
            1,
            NotificationTrigger::Missed,
            '22:00',
            '07:00'
        );

        self::assertCount(1, $service->rules(10));
        self::assertSame('22:00', $service->rules(10)[0]->quietStart);
        self::assertSame('07:00', $service->rules(10)[0]->quietEnd);

        try {
            $service->createRule(
                10,
                $channelId,
                1,
                NotificationTrigger::Missed,
                '22:00',
                '07:00'
            );
            self::fail('Duplicate notification rule was accepted.');
        } catch (RuntimeException) {
            self::assertCount(1, $service->rules(10));
        }

        $this->expectException(RuntimeException::class);
        $service->createRule(
            10,
            $channelId,
            999,
            NotificationTrigger::Incoming
        );
    }

    public function testInvalidQuietHoursAreRejected(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $devices = new InMemoryDeviceRepository();
        $service = new NotificationAdministrationService(
            $channels,
            new InMemoryNotificationRuleRepository(),
            $devices,
            new NotificationConfigCipher(
                new SecretCipher(self::APP_KEY)
            ),
            new InMemoryNotificationDeliveryRepository(),
            NotificationMessageCipher::fromApplicationKey(
                self::APP_KEY
            ),
            new FrozenClock('2026-10-20 10:00:00')
        );
        $channelId = $service->createChannel(
            10,
            'Mail',
            'email',
            [
                'recipient' => 'user@example.test',
                'from' => 'phonebook@example.test',
            ]
        );

        $this->expectException(RuntimeException::class);
        $service->createRule(
            10,
            $channelId,
            null,
            NotificationTrigger::Incoming,
            '22:00',
            null
        );
    }

    public function testTestSendQueuesAndFailedDeliveryCanBeRetried(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $deliveries = new InMemoryNotificationDeliveryRepository();
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $service = new NotificationAdministrationService(
            $channels,
            new InMemoryNotificationRuleRepository(),
            new InMemoryDeviceRepository(),
            new NotificationConfigCipher(
                new SecretCipher(self::APP_KEY)
            ),
            $deliveries,
            NotificationMessageCipher::fromApplicationKey(
                self::APP_KEY
            ),
            $clock
        );
        $channelId = $service->createChannel(
            10,
            'Mail',
            'email',
            [
                'recipient' => 'user@example.test',
                'from' => 'phonebook@example.test',
            ]
        );

        $deliveryId = $service->testChannel(10, $channelId);
        self::assertSame(
            NotificationTrigger::Test,
            $deliveries->get($deliveryId)->trigger
        );

        $claimed = $deliveries->claimDue($clock->now(), 10);
        self::assertCount(1, $claimed);
        $deliveries->markFailed($deliveryId, $clock->now());

        $history = $service->deliveryHistory(10);
        self::assertCount(1, $history->items);
        self::assertTrue($history->items[0]->canRetry);

        $service->retryDelivery(10, $deliveryId);

        self::assertSame(
            'pending',
            $deliveries->get($deliveryId)->status->value
        );
        self::assertSame(0, $deliveries->get($deliveryId)->attempts);
    }


    public function testDeliveryHistorySupportsFiltersAndCursorPagination(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $deliveries = new InMemoryNotificationDeliveryRepository();
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $service = new NotificationAdministrationService(
            $channels,
            new InMemoryNotificationRuleRepository(),
            new InMemoryDeviceRepository(),
            new NotificationConfigCipher(
                new SecretCipher(self::APP_KEY)
            ),
            $deliveries,
            NotificationMessageCipher::fromApplicationKey(
                self::APP_KEY
            ),
            $clock
        );
        $mailId = $service->createChannel(
            10,
            'Mail',
            'email',
            [
                'recipient' => 'user@example.test',
                'from' => 'phonebook@example.test',
            ]
        );
        $pushId = $service->createChannel(
            10,
            'Push',
            'pushover',
            [
                'token' => str_repeat('A', 30),
                'user' => str_repeat('B', 30),
            ]
        );

        $first = $service->testChannel(10, $mailId);
        $second = $service->testChannel(10, $mailId);
        $third = $service->testChannel(10, $pushId);

        $deliveries->claimDue($clock->now(), 10);
        $deliveries->markSent($first, $clock->now());
        $deliveries->markFailed($second, $clock->now());

        $failed = $service->deliveryHistory(
            10,
            new NotificationDeliveryHistoryFilter(
                status: NotificationDeliveryStatus::Failed
            )
        );

        self::assertCount(1, $failed->items);
        self::assertSame($second, $failed->items[0]->id);

        $mail = $service->deliveryHistory(
            10,
            new NotificationDeliveryHistoryFilter(
                channelId: $mailId
            ),
            1
        );

        self::assertCount(1, $mail->items);
        self::assertSame($second, $mail->items[0]->id);
        self::assertSame($second, $mail->olderBeforeId);

        $older = $service->deliveryHistory(
            10,
            new NotificationDeliveryHistoryFilter(
                channelId: $mailId,
                beforeId: $mail->olderBeforeId
            ),
            1
        );

        self::assertCount(1, $older->items);
        self::assertSame($first, $older->items[0]->id);
        self::assertNull($older->olderBeforeId);

        $otherChannel = $service->deliveryHistory(
            10,
            new NotificationDeliveryHistoryFilter(
                channelId: $pushId
            )
        );

        self::assertCount(1, $otherChannel->items);
        self::assertSame($third, $otherChannel->items[0]->id);
    }

    public function testOtherUserCannotRetryFailedDelivery(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $deliveries = new InMemoryNotificationDeliveryRepository();
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $service = new NotificationAdministrationService(
            $channels,
            new InMemoryNotificationRuleRepository(),
            new InMemoryDeviceRepository(),
            new NotificationConfigCipher(
                new SecretCipher(self::APP_KEY)
            ),
            $deliveries,
            NotificationMessageCipher::fromApplicationKey(
                self::APP_KEY
            ),
            $clock
        );
        $channelId = $service->createChannel(
            10,
            'Mail',
            'email',
            [
                'recipient' => 'user@example.test',
                'from' => 'phonebook@example.test',
            ]
        );
        $deliveryId = $service->testChannel(10, $channelId);
        $deliveries->claimDue($clock->now(), 10);
        $deliveries->markFailed($deliveryId, $clock->now());

        $this->expectException(RuntimeException::class);
        $service->retryDelivery(20, $deliveryId);
    }

    public function testWebPushReregistrationKeepsChannelAndRules(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $rules = new InMemoryNotificationRuleRepository();
        $service = new NotificationAdministrationService(
            $channels,
            $rules,
            new InMemoryDeviceRepository(),
            new NotificationConfigCipher(
                new SecretCipher(self::APP_KEY)
            ),
            new InMemoryNotificationDeliveryRepository(),
            NotificationMessageCipher::fromApplicationKey(
                self::APP_KEY
            ),
            new FrozenClock('2026-10-20 10:00:00')
        );
        $browserId = '0123456789abcdef0123456789abcdef';
        $channelId = $service->registerWebPush(
            10,
            'https://fcm.googleapis.com/fcm/send/first-token',
            'BabcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-',
            'abcdefghijklmnop',
            'aes128gcm',
            $browserId
        );
        $service->createRule(
            10,
            $channelId,
            null,
            NotificationTrigger::Missed
        );
        self::assertTrue(
            $service->webPushActive(
                10,
                $browserId,
                'https://fcm.googleapis.com/fcm/send/first-token'
            )
        );

        $service->disableWebPush(
            10,
            $browserId,
            'https://fcm.googleapis.com/fcm/send/first-token'
        );

        self::assertFalse(
            $service->webPushActive(
                10,
                $browserId,
                'https://fcm.googleapis.com/fcm/send/first-token'
            )
        );

        $secondId = $service->registerWebPush(
            10,
            'https://fcm.googleapis.com/fcm/send/second-token',
            'CabcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-',
            'ponmlkjihgfedcba',
            'aes128gcm',
            $browserId
        );

        self::assertSame($channelId, $secondId);
        self::assertTrue(
            $channels->findOwnedBy($channelId, 10)?->isActive
        );
        self::assertTrue(
            $service->webPushActive(
                10,
                $browserId,
                'https://fcm.googleapis.com/fcm/send/second-token'
            )
        );
        self::assertCount(1, $service->rules(10));
    }

    public function testTestTriggerCannotBecomeRule(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $service = new NotificationAdministrationService(
            $channels,
            new InMemoryNotificationRuleRepository(),
            new InMemoryDeviceRepository(),
            new NotificationConfigCipher(
                new SecretCipher(self::APP_KEY)
            ),
            new InMemoryNotificationDeliveryRepository(),
            NotificationMessageCipher::fromApplicationKey(
                self::APP_KEY
            ),
            new FrozenClock('2026-10-20 10:00:00')
        );
        $channelId = $service->createChannel(
            10,
            'Mail',
            'email',
            [
                'recipient' => 'user@example.test',
                'from' => 'phonebook@example.test',
            ]
        );

        $this->expectException(RuntimeException::class);
        $service->createRule(
            10,
            $channelId,
            null,
            NotificationTrigger::Test
        );
    }

    public function testOtherUsersCannotUseChannel(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $service = new NotificationAdministrationService(
            $channels,
            new InMemoryNotificationRuleRepository(),
            new InMemoryDeviceRepository(),
            new NotificationConfigCipher(
                new SecretCipher(self::APP_KEY)
            ),
            new InMemoryNotificationDeliveryRepository(),
            NotificationMessageCipher::fromApplicationKey(
                self::APP_KEY
            ),
            new FrozenClock('2026-10-20 10:00:00')
        );
        $channelId = $service->createChannel(
            10,
            'Mail',
            'email',
            [
                'recipient' => 'user@example.test',
                'from' => 'phonebook@example.test',
            ]
        );

        $this->expectException(RuntimeException::class);
        $service->createRule(
            20,
            $channelId,
            null,
            NotificationTrigger::Missed
        );
    }
}
