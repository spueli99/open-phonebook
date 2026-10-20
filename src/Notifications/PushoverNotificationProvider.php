<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use JsonException;
use OpenPhonebook\Http\HttpClientInterface;
use RuntimeException;

final class PushoverNotificationProvider
    implements NotificationProviderInterface
{
    private const ENDPOINT = 'https://api.pushover.net/1/messages.json';

    public function __construct(
        private readonly HttpClientInterface $http
    ) {
    }

    public function name(): string
    {
        return 'pushover';
    }

    public function send(
        array $config,
        NotificationMessage $message
    ): void {
        $token = $config['token'] ?? '';
        $user = $config['user'] ?? '';

        if (
            preg_match('/^[A-Za-z0-9]{30}$/', $token) !== 1
            || preg_match('/^[A-Za-z0-9]{30}$/', $user) !== 1
        ) {
            throw new RuntimeException(
                'Invalid Pushover configuration.'
            );
        }

        if (
            mb_strlen($message->title) > 250
            || mb_strlen($message->body) > 1024
        ) {
            throw new RuntimeException(
                'Pushover message exceeds provider limits.'
            );
        }

        $response = $this->http->postForm(
            self::ENDPOINT,
            [],
            [
                'token' => $token,
                'user' => $user,
                'title' => $message->title,
                'message' => $message->body,
            ]
        );

        if ($response->statusCode !== 200) {
            throw new RuntimeException(
                'Pushover request was rejected.'
            );
        }

        try {
            $payload = json_decode(
                $response->body,
                true,
                16,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Invalid Pushover response.',
                0,
                $exception
            );
        }

        if (
            !is_array($payload)
            || ($payload['status'] ?? null) !== 1
        ) {
            throw new RuntimeException(
                'Pushover did not accept the message.'
            );
        }
    }
}
