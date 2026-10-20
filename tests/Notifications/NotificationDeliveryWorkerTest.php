<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Notifications;

use DateInterval;
use OpenPhonebook\Notifications\NotificationConfigCipher;
use OpenPhonebook\Notifications\NotificationDeliveryStatus;
use OpenPhonebook\Notifications\NotificationDeliveryWorker;
use OpenPhonebook\Notifications\NotificationMessage;
use OpenPhonebook\Notifications\NotificationMessageCipher;
use OpenPhonebook\Notifications\NotificationProviderRegistry;
use OpenPhonebook\Notifications\NotificationTrigger;
use OpenPhonebook\Notifications\WebPushNotificationProvider;
use OpenPhonebook\Notifications\WebPushTransportResult;
use OpenPhonebook\Security\SecretCipher;
use OpenPhonebook\Tests\Support\Clock\FrozenClock;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationChannelRepository;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationDeliveryRepository;
use OpenPhonebook\Tests\Support\Notifications\RecordingNotificationProvider;
use OpenPhonebook\Tests\Support\Notifications\RecordingWebPushTransport;
use PHPUnit\Framework\TestCase;

final class NotificationDeliveryWorkerTest extends TestCase
{
    private const APP_KEY =
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testSuccessfulDeliveryIsMarkedSent(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $deliveries = new InMemoryNotificationDeliveryRepository();
        $channels = new InMemoryNotificationChannelRepository();
        $cipher = new NotificationConfigCipher(
            new SecretCipher(self::APP_KEY)
        );
        $channelId = $channels->create(
            10,
            'Push',
            'recording',
            $cipher->encrypt(['target' => 'one'])
        );
        $message = new NotificationMessage(
            'Missed call',
            'Missed call from Alice'
        );
        $id = $deliveries->enqueue(
            10,
            $channelId,
            NotificationTrigger::Missed,
            $message,
            NotificationMessageCipher::fromApplicationKey(
                self::APP_KEY
            )->encrypt($message),
            $clock->now()
        );
        $provider = new RecordingNotificationProvider('recording');
        $worker = new NotificationDeliveryWorker(
            $deliveries,
            $channels,
            new NotificationProviderRegistry([$provider]),
            $cipher,
            $clock
        );

        $result = $worker->run();

        self::assertSame(1, $result->sent);
        self::assertSame(
            NotificationDeliveryStatus::Sent,
            $deliveries->get($id)->status
        );
        self::assertCount(1, $provider->sent);
    }

    public function testFailureIsRetriedThenEventuallyFails(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $deliveries = new InMemoryNotificationDeliveryRepository();
        $channels = new InMemoryNotificationChannelRepository();
        $cipher = new NotificationConfigCipher(
            new SecretCipher(self::APP_KEY)
        );
        $channelId = $channels->create(
            10,
            'Failing',
            'recording',
            $cipher->encrypt(['target' => 'one'])
        );
        $message = new NotificationMessage('Missed call', 'Body');
        $id = $deliveries->enqueue(
            10,
            $channelId,
            NotificationTrigger::Missed,
            $message,
            NotificationMessageCipher::fromApplicationKey(
                self::APP_KEY
            )->encrypt($message),
            $clock->now()
        );
        $worker = new NotificationDeliveryWorker(
            $deliveries,
            $channels,
            new NotificationProviderRegistry([
                new RecordingNotificationProvider('recording', true),
            ]),
            $cipher,
            $clock
        );

        $first = $worker->run();
        self::assertSame(1, $first->rescheduled);
        self::assertSame(
            NotificationDeliveryStatus::Pending,
            $deliveries->get($id)->status
        );

        foreach ([60, 300, 900, 3600] as $seconds) {
            $clock->advance(new DateInterval('PT' . $seconds . 'S'));
            $result = $worker->run();
        }

        self::assertSame(1, $result->failed);
        self::assertSame(
            NotificationDeliveryStatus::Failed,
            $deliveries->get($id)->status
        );
        self::assertSame(5, $deliveries->get($id)->attempts);
    }

    public function testExpiredWebPushChannelIsDisabledAndCancelled(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $deliveries = new InMemoryNotificationDeliveryRepository();
        $channels = new InMemoryNotificationChannelRepository();
        $cipher = new NotificationConfigCipher(
            new SecretCipher(self::APP_KEY)
        );
        $channelId = $channels->create(
            10,
            'Web Push',
            'web_push',
            $cipher->encrypt([
                'endpoint' =>
                    'https://fcm.googleapis.com/fcm/send/example-token',
                'p256dh' =>
                    'BabcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-',
                'auth' => 'abcdefghijklmnop',
                'content_encoding' => 'aes128gcm',
                'browser_id' =>
                    '0123456789abcdef0123456789abcdef',
            ])
        );
        $message = new NotificationMessage('Incoming call', 'Body');
        $id = $deliveries->enqueue(
            10,
            $channelId,
            NotificationTrigger::Incoming,
            $message,
            NotificationMessageCipher::fromApplicationKey(
                self::APP_KEY
            )->encrypt($message),
            $clock->now()
        );
        $worker = new NotificationDeliveryWorker(
            $deliveries,
            $channels,
            new NotificationProviderRegistry([
                new WebPushNotificationProvider(
                    new RecordingWebPushTransport(
                        WebPushTransportResult::Expired
                    )
                ),
            ]),
            $cipher,
            $clock
        );

        $result = $worker->run();

        self::assertSame(1, $result->cancelled);
        self::assertSame(
            NotificationDeliveryStatus::Cancelled,
            $deliveries->get($id)->status
        );
        self::assertFalse(
            $channels->findOwnedBy($channelId, 10)?->isActive
        );
    }

    public function testDisabledChannelCancelsQueuedDelivery(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $deliveries = new InMemoryNotificationDeliveryRepository();
        $channels = new InMemoryNotificationChannelRepository();
        $cipher = new NotificationConfigCipher(
            new SecretCipher(self::APP_KEY)
        );
        $channelId = $channels->create(
            10,
            'Push',
            'recording',
            $cipher->encrypt(['target' => 'one'])
        );
        $message = new NotificationMessage('Incoming call', 'Body');
        $id = $deliveries->enqueue(
            10,
            $channelId,
            NotificationTrigger::Incoming,
            $message,
            NotificationMessageCipher::fromApplicationKey(
                self::APP_KEY
            )->encrypt($message),
            $clock->now()
        );
        $channels->setActive($channelId, 10, false);
        $worker = new NotificationDeliveryWorker(
            $deliveries,
            $channels,
            new NotificationProviderRegistry([
                new RecordingNotificationProvider('recording'),
            ]),
            $cipher,
            $clock
        );

        $result = $worker->run();

        self::assertSame(1, $result->cancelled);
        self::assertSame(
            NotificationDeliveryStatus::Cancelled,
            $deliveries->get($id)->status
        );
    }
}
