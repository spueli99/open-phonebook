<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Notifications;

use OpenPhonebook\Http\HttpResponse;
use OpenPhonebook\Notifications\EmailNotificationProvider;
use OpenPhonebook\Notifications\NotificationMessage;
use OpenPhonebook\Notifications\PushoverNotificationProvider;
use OpenPhonebook\Tests\Support\Http\FakeHttpClient;
use OpenPhonebook\Tests\Support\Notifications\FakeMailTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NotificationProviderTest extends TestCase
{
    public function testPushoverSendsExpectedForm(): void
    {
        $http = new FakeHttpClient();
        $http->queue(new HttpResponse(200, '{"status":1}'));
        $provider = new PushoverNotificationProvider($http);

        $provider->send(
            [
                'token' => str_repeat('A', 30),
                'user' => str_repeat('B', 30),
            ],
            new NotificationMessage(
                'Incoming call',
                'Incoming call from Alice'
            )
        );

        self::assertCount(1, $http->requests);
        self::assertSame(
            'https://api.pushover.net/1/messages.json',
            $http->requests[0]['url']
        );
        self::assertSame(
            'Incoming call',
            $http->requests[0]['form']['title']
        );
    }

    public function testPushoverRejectsProviderFailure(): void
    {
        $http = new FakeHttpClient();
        $http->queue(new HttpResponse(429, '{"status":0}'));

        $this->expectException(RuntimeException::class);

        (new PushoverNotificationProvider($http))->send(
            [
                'token' => str_repeat('A', 30),
                'user' => str_repeat('B', 30),
            ],
            new NotificationMessage('Title', 'Body')
        );
    }

    public function testEmailUsesConfiguredAddresses(): void
    {
        $transport = new FakeMailTransport();
        $provider = new EmailNotificationProvider($transport);

        $provider->send(
            [
                'recipient' => 'user@example.test',
                'from' => 'phonebook@example.test',
            ],
            new NotificationMessage('Missed call', 'Missed call from Alice')
        );

        self::assertCount(1, $transport->messages);
        self::assertSame(
            'user@example.test',
            $transport->messages[0]['recipient']
        );
        self::assertSame(
            'phonebook@example.test',
            $transport->messages[0]['from']
        );
    }
}
