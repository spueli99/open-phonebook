<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use RuntimeException;

final class WebPushSubscriptionValidator
{
    /**
     * @var list<string>
     */
    private const ALLOWED_HOST_SUFFIXES = [
        'fcm.googleapis.com',
        'push.services.mozilla.com',
        'web.push.apple.com',
    ];

    /**
     * @param array<string, string> $config
     */
    public static function fromConfig(array $config): WebPushSubscriptionData
    {
        return self::validate(
            $config['endpoint'] ?? '',
            $config['p256dh'] ?? '',
            $config['auth'] ?? '',
            $config['content_encoding'] ?? ''
        );
    }

    public static function validate(
        string $endpoint,
        string $p256dh,
        string $auth,
        string $contentEncoding
    ): WebPushSubscriptionData {
        $endpoint = trim($endpoint);
        $p256dh = trim($p256dh);
        $auth = trim($auth);
        $contentEncoding = trim($contentEncoding);

        if (
            strlen($endpoint) > 4096
            || filter_var($endpoint, FILTER_VALIDATE_URL) === false
        ) {
            throw new RuntimeException(
                'Invalid Web Push endpoint.'
            );
        }

        $parts = parse_url($endpoint);

        if (
            !is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || !isset($parts['host'])
            || !is_string($parts['host'])
            || !self::hostAllowed($parts['host'])
        ) {
            throw new RuntimeException(
                'Unsupported Web Push endpoint.'
            );
        }

        if (
            !self::isBase64Url($p256dh, 40, 140)
            || !self::isBase64Url($auth, 12, 64)
        ) {
            throw new RuntimeException(
                'Invalid Web Push subscription keys.'
            );
        }

        if (
            !in_array(
                $contentEncoding,
                ['aes128gcm', 'aesgcm'],
                true
            )
        ) {
            throw new RuntimeException(
                'Unsupported Web Push content encoding.'
            );
        }

        return new WebPushSubscriptionData(
            $endpoint,
            $p256dh,
            $auth,
            $contentEncoding
        );
    }

    private static function hostAllowed(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));

        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if (
                $host === $suffix
                || str_ends_with($host, '.' . $suffix)
            ) {
                return true;
            }
        }

        return false;
    }

    private static function isBase64Url(
        string $value,
        int $minLength,
        int $maxLength
    ): bool {
        $length = strlen($value);

        return $length >= $minLength
            && $length <= $maxLength
            && preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1;
    }

    private function __construct()
    {
    }
}
