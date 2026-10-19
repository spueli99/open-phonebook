<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

use OpenPhonebook\Clock\ClockInterface;
use RuntimeException;

final class GoogleOAuthStateStore
{
    private const SESSION_KEY = '_open_phonebook_google_oauth';
    private const TTL_SECONDS = 600;

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly string $applicationKey,
    ) {
    }

    public function create(
        int $addressBookId,
        int $userId
    ): GoogleOAuthState {
        $this->requireSession();

        $state = $this->randomBase64Url(32);
        $codeVerifier = $this->randomBase64Url(64);

        $_SESSION[self::SESSION_KEY] = [
            'state_hash' => $this->stateHash($state),
            'code_verifier' => $codeVerifier,
            'address_book_id' => $addressBookId,
            'user_id' => $userId,
            'expires_at' => $this->clock->now()->getTimestamp()
                + self::TTL_SECONDS,
        ];

        return new GoogleOAuthState($state, $codeVerifier);
    }

    /**
     * @return array{address_book_id: int, code_verifier: string}|null
     */
    public function consume(string $state, int $userId): ?array
    {
        $this->requireSession();
        $stored = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($stored)) {
            return null;
        }

        $stateHash = $stored['state_hash'] ?? null;
        $codeVerifier = $stored['code_verifier'] ?? null;
        $addressBookId = $stored['address_book_id'] ?? null;
        $storedUserId = $stored['user_id'] ?? null;
        $expiresAt = $stored['expires_at'] ?? null;

        unset($_SESSION[self::SESSION_KEY]);

        if (
            !is_string($stateHash)
            || !is_string($codeVerifier)
            || !is_int($addressBookId)
            || !is_int($storedUserId)
            || !is_int($expiresAt)
            || $storedUserId !== $userId
            || $expiresAt <= $this->clock->now()->getTimestamp()
            || !hash_equals($stateHash, $this->stateHash($state))
        ) {
            return null;
        }

        return [
            'address_book_id' => $addressBookId,
            'code_verifier' => $codeVerifier,
        ];
    }

    private function stateHash(string $state): string
    {
        return hash_hmac('sha256', $state, $this->applicationKey);
    }

    private function randomBase64Url(int $bytes): string
    {
        return rtrim(
            strtr(base64_encode(random_bytes($bytes)), '+/', '-_'),
            '='
        );
    }

    private function requireSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException(
                'A session is required for Google OAuth.'
            );
        }
    }
}
