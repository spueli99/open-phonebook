<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

final readonly class GoogleTokenSet
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresIn,
    ) {
    }
}
