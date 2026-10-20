<?php

declare(strict_types=1);

namespace OpenPhonebook\Audit;

use InvalidArgumentException;

final class AuditMetadata
{
    /** @var list<string> */
    private const FORBIDDEN_KEY_PARTS = [
        'password',
        'token',
        'secret',
        'session',
        'cookie',
        'authorization',
        'email',
        'phone',
        'contact',
        'content',
        'body',
    ];

    /**
     * @param array<string, bool|int|string> $metadata
     * @return array<string, bool|int|string>
     */
    public static function validate(array $metadata): array
    {
        if (count($metadata) > 8) {
            throw new InvalidArgumentException(
                'Audit metadata may contain at most eight entries.'
            );
        }

        foreach ($metadata as $key => $value) {
            if (
                !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)
                || self::containsForbiddenPart($key)
            ) {
                throw new InvalidArgumentException(
                    'Audit metadata key is invalid.'
                );
            }

            if (is_string($value) && strlen($value) > 120) {
                throw new InvalidArgumentException(
                    'Audit metadata value is too long.'
                );
            }
        }

        return $metadata;
    }

    private static function containsForbiddenPart(string $key): bool
    {
        foreach (self::FORBIDDEN_KEY_PARTS as $part) {
            if (str_contains($key, $part)) {
                return true;
            }
        }

        return false;
    }

    private function __construct()
    {
    }
}
