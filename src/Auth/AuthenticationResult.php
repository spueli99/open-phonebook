<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

final readonly class AuthenticationResult
{
    private function __construct(
        public bool $successful,
        public ?AuthenticatedUser $user,
    ) {
    }

    public static function success(AuthenticatedUser $user): self
    {
        return new self(true, $user);
    }

    public static function failure(): self
    {
        return new self(false, null);
    }
}
