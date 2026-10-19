<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

final readonly class GoogleOAuthState
{
    public function __construct(
        public string $state,
        public string $codeVerifier,
    ) {
    }

    public function codeChallenge(): string
    {
        return rtrim(
            strtr(
                base64_encode(
                    hash('sha256', $this->codeVerifier, true)
                ),
                '+/',
                '-_'
            ),
            '='
        );
    }
}
