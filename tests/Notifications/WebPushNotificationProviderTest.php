<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Notifications;

use OpenPhonebook\Notifications\ExpiredNotificationChannelException;
use OpenPhonebook\Notifications\NotificationMessage;
use OpenPhonebook\Notifications\WebPushNotificationProvider;
use OpenPhonebook\Notifications\WebPushSubscriptionValidator;
use OpenPhonebook\Notifications\WebPushTransportResult;
use OpenPhonebook\Tests\Support\Notifications\RecordingWebPushTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WebPushNotificationProviderTest extends TestCase
{
    private const ENDPOINT =
        'https://fcm.googleapis.com/fcm/send/example-token';
    private const P256DH =
        'BabcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
    private const AUTH = 'abcdefghijklmnop';
    private const BROWSER_ID = '0123456789abcdef0123456789abcdef';

    public function testValidSubscriptionIsSent(): void
    {
        $transport = new RecordingWebPushTransport();
        $provider = new WebPushNotificationProvider($transport);

        $provider->send(
            $this->config(),
            new NotificationMessage('Title', 'Body')
        );

        self::assertCount(1, $transport->sent);
        self::assertSame(
            self::ENDPOINT,
            $transport->sent[0]['subscription']->endpoint
        );
    }

    public function testExpiredSubscriptionIsReportedSeparately(): void
    {
        $provider = new WebPushNotificationProvider(
            new RecordingWebPushTransport(
                WebPushTransportResult::Expired
            )
        );

        $this->expectException(
            ExpiredNotificationChannelException::class
        );
        $provider->send(
            $this->config(),
            new NotificationMessage('Title', 'Body')
        );
    }

    public function testArbitraryHttpsEndpointIsRejected(): void
    {
        $this->expectException(RuntimeException::class);

        WebPushSubscriptionValidator::validate(
            'https://example.com/internal-endpoint',
            self::P256DH,
            self::AUTH,
            'aes128gcm',
            self::BROWSER_ID
        );
    }

    /**
     * @return array<string, string>
     */
    private function config(): array
    {
        return WebPushSubscriptionValidator::validate(
            self::ENDPOINT,
            self::P256DH,
            self::AUTH,
            'aes128gcm',
            self::BROWSER_ID
        )->toConfig();
    }
}
