<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

final class AuthenticationService
{
    private const DUMMY_PASSWORD_HASH =
        '$2y$12$YVBRlEZfC6KRsST5BgOGOe8r0dm2r8NDbClMinIOAvbnZXnNGJgwi';

    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function authenticate(string $email, string $password): AuthenticationResult
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            password_verify($password, self::DUMMY_PASSWORD_HASH);

            return AuthenticationResult::failure();
        }

        if (
            !$user->isActive
            || $user->isDeleted
            || !password_verify($password, $user->passwordHash)
        ) {
            return AuthenticationResult::failure();
        }

        if (password_needs_rehash($user->passwordHash, PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash(
                $user->id,
                password_hash($password, PASSWORD_DEFAULT)
            );
        }

        return AuthenticationResult::success($user->toAuthenticatedUser());
    }
}
