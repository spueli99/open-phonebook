<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use RuntimeException;

final readonly class WebPushServerConfiguration
{
    public function __construct(
        public string $subject,
        public string $publicKey,
        public string $privateKey,
    ) {
        if (
            !self::validSubject($subject)
            || !self::base64Url($publicKey, 80, 100)
            || !self::base64Url($privateKey, 40, 60)
        ) {
            throw new RuntimeException(
                'Invalid Web Push VAPID configuration.'
            );
        }
    }

    private static function validSubject(string $subject): bool
    {
        return str_starts_with($subject, 'mailto:')
            ? filter_var(
                substr($subject, 7),
                FILTER_VALIDATE_EMAIL
            ) !== false
            : (
                filter_var($subject, FILTER_VALIDATE_URL) !== false
                && str_starts_with($subject, 'https://')
            );
    }

    private static function base64Url(
        string $value,
        int $minLength,
        int $maxLength
    ): bool {
        $length = strlen($value);

        return $length >= $minLength
            && $length <= $maxLength
            && preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1;
    }
}
