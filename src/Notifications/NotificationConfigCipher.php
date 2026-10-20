<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use JsonException;
use OpenPhonebook\Security\SecretCipher;
use RuntimeException;

final class NotificationConfigCipher
{
    public function __construct(
        private readonly SecretCipher $cipher
    ) {
    }

    /**
     * @param array<string, string> $config
     */
    public function encrypt(array $config): string
    {
        try {
            $json = json_encode(
                $config,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Notification configuration could not be encoded.',
                0,
                $exception
            );
        }

        return $this->cipher->encrypt($json);
    }

    /**
     * @return array<string, string>
     */
    public function decrypt(string $encrypted): array
    {
        try {
            $decoded = json_decode(
                $this->cipher->decrypt($encrypted),
                true,
                16,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Notification configuration could not be decoded.',
                0,
                $exception
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Notification configuration is invalid.'
            );
        }

        $config = [];

        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new RuntimeException(
                    'Notification configuration is invalid.'
                );
            }

            $config[$key] = $value;
        }

        return $config;
    }
}
