<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use JsonException;
use OpenPhonebook\Security\SecretCipher;
use RuntimeException;

final class NotificationMessageCipher
{
    private const CONTEXT =
        'open-phonebook/notification-retry-payload/v1';

    private function __construct(
        private readonly SecretCipher $cipher
    ) {
    }

    public static function fromApplicationKey(
        string $applicationKey
    ): self {
        return new self(
            new SecretCipher(
                $applicationKey,
                self::CONTEXT
            )
        );
    }

    public function encrypt(NotificationMessage $message): string
    {
        try {
            $json = json_encode(
                [
                    'title' => $message->title,
                    'body' => $message->body,
                ],
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Notification message could not be encoded.',
                0,
                $exception
            );
        }

        return $this->cipher->encrypt($json);
    }

    public function decrypt(string $encrypted): NotificationMessage
    {
        try {
            $decoded = json_decode(
                $this->cipher->decrypt($encrypted),
                true,
                8,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Notification message could not be decoded.',
                0,
                $exception
            );
        }

        if (
            !is_array($decoded)
            || !isset($decoded['title'], $decoded['body'])
            || !is_string($decoded['title'])
            || !is_string($decoded['body'])
            || $decoded['title'] === ''
            || $decoded['body'] === ''
        ) {
            throw new RuntimeException(
                'Notification message is invalid.'
            );
        }

        return new NotificationMessage(
            $decoded['title'],
            $decoded['body']
        );
    }
}
