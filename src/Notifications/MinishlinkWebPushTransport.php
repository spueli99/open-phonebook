<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use JsonException;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

final class MinishlinkWebPushTransport implements WebPushTransportInterface
{
    private WebPush $webPush;

    public function __construct(WebPushServerConfiguration $configuration)
    {
        $client = new Client([
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::CONNECT_TIMEOUT => 10,
            RequestOptions::TIMEOUT => 20,
        ]);

        $this->webPush = new WebPush(
            [
                'VAPID' => [
                    'subject' => $configuration->subject,
                    'publicKey' => $configuration->publicKey,
                    'privateKey' => $configuration->privateKey,
                ],
            ],
            [
                'TTL' => 300,
                'urgency' => 'high',
                'contentType' => 'application/json',
            ],
            $client
        );
    }

    public function send(
        WebPushSubscriptionData $subscription,
        NotificationMessage $message
    ): WebPushTransportResult {
        try {
            $payload = json_encode(
                [
                    'title' => $message->title,
                    'body' => $message->body,
                    'url' => '/notifications/',
                ],
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Web Push payload could not be encoded.',
                0,
                $exception
            );
        }

        $report = $this->webPush->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'keys' => [
                    'p256dh' => $subscription->p256dh,
                    'auth' => $subscription->auth,
                ],
                'contentEncoding' =>
                    $subscription->contentEncoding,
            ]),
            $payload
        );

        if ($report->isSuccess()) {
            return WebPushTransportResult::Sent;
        }

        if ($report->isSubscriptionExpired()) {
            return WebPushTransportResult::Expired;
        }

        throw new RuntimeException(
            'Web Push service rejected the notification.'
        );
    }
}
