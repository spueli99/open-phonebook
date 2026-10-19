<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

final class AuthenticationService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function authenticate(string $email, string $password): AuthenticationResult
    {
        $user = $this->users->findByEmail($email);

        if (
            $user === null
            || !$user->isActive
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
