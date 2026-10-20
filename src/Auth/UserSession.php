<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

use OpenPhonebook\Security\Session;
use RuntimeException;

final class UserSession
{
    private const KEY = '_open_phonebook_user';

    public function __construct(
        private readonly ?UserRepositoryInterface $users = null
    ) {
    }

    public function login(AuthenticatedUser $user): void
    {
        $this->requireSession();
        Session::regenerate();

        $_SESSION[self::KEY] = [
            'id' => $user->id,
            'email' => $user->email,
            'display_name' => $user->displayName,
            'role' => $user->role,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'auth_revision' => $user->authRevision,
            'authenticated_at' => time(),
        ];
    }

    public function user(): ?AuthenticatedUser
    {
        $this->requireSession();
        $value = $_SESSION[self::KEY] ?? null;

        if (!is_array($value)) {
            return null;
        }

        $required = [
            'id',
            'email',
            'display_name',
            'role',
            'locale',
            'timezone',
            'auth_revision',
        ];
        foreach ($required as $key) {
            if (!array_key_exists($key, $value)) {
                return null;
            }
        }

        $sessionUser = new AuthenticatedUser(
            (int) $value['id'],
            (string) $value['email'],
            (string) $value['display_name'],
            (string) $value['role'],
            (string) $value['locale'],
            (string) $value['timezone'],
            (int) $value['auth_revision'],
        );

        if ($this->users === null) {
            return $sessionUser;
        }

        $current = $this->users->findById($sessionUser->id);

        if (
            $current === null
            || !$current->isActive
            || $current->isDeleted
            || $current->authRevision !== $sessionUser->authRevision
        ) {
            unset($_SESSION[self::KEY]);
            Session::regenerate();

            return null;
        }

        return $current->toAuthenticatedUser();
    }

    public function isAuthenticated(): bool
    {
        return $this->user() !== null;
    }

    public function logout(): void
    {
        $this->requireSession();
        unset($_SESSION[self::KEY]);
        Session::regenerate();
    }

    private function requireSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('A session is required for authentication.');
        }
    }
}
