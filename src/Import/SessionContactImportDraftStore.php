<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use OpenPhonebook\Clock\ClockInterface;
use RuntimeException;

final class SessionContactImportDraftStore implements ContactImportDraftStoreInterface
{
    private const SESSION_KEY = '_open_phonebook_contact_import_draft';
    private const MAX_CONTENT_BYTES = 2_000_000;

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly string $applicationKey,
    ) {
    }

    public function create(
        int $addressBookId,
        int $userId,
        string $contents
    ): string {
        $this->requireSession();

        if (
            $contents === ''
            || strlen($contents) > self::MAX_CONTENT_BYTES
        ) {
            throw new RuntimeException('Import draft contents are invalid.');
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = $this->clock->now()->add(new DateInterval('PT15M'));

        $_SESSION[self::SESSION_KEY] = [
            'token_hash' => $this->tokenHash($token),
            'address_book_id' => $addressBookId,
            'user_id' => $userId,
            'contents' => $contents,
            'expires_at' => $expiresAt->getTimestamp(),
        ];

        return $token;
    }

    public function resolve(
        string $token,
        int $addressBookId,
        int $userId
    ): ?ContactImportDraft {
        $this->requireSession();
        $value = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($value)) {
            return null;
        }

        $tokenHash = $value['token_hash'] ?? null;
        $storedAddressBookId = $value['address_book_id'] ?? null;
        $storedUserId = $value['user_id'] ?? null;
        $contents = $value['contents'] ?? null;
        $expiresAt = $value['expires_at'] ?? null;

        if (
            !is_string($tokenHash)
            || !is_int($storedAddressBookId)
            || !is_int($storedUserId)
            || !is_string($contents)
            || !is_int($expiresAt)
        ) {
            $this->clear();

            return null;
        }

        if (
            !hash_equals($tokenHash, $this->tokenHash($token))
            || $storedAddressBookId !== $addressBookId
            || $storedUserId !== $userId
        ) {
            return null;
        }

        if ($expiresAt <= $this->clock->now()->getTimestamp()) {
            $this->clear();

            return null;
        }

        return new ContactImportDraft(
            $storedAddressBookId,
            $storedUserId,
            $contents,
            (new DateTimeImmutable('@' . $expiresAt))->setTimezone(
                new DateTimeZone('UTC')
            ),
        );
    }

    public function discard(string $token): void
    {
        $this->requireSession();
        $value = $_SESSION[self::SESSION_KEY] ?? null;

        if (
            is_array($value)
            && is_string($value['token_hash'] ?? null)
            && hash_equals(
                $value['token_hash'],
                $this->tokenHash($token)
            )
        ) {
            $this->clear();
        }
    }

    private function tokenHash(string $token): string
    {
        return hash_hmac('sha256', $token, $this->applicationKey);
    }

    private function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    private function requireSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException(
                'A session is required for contact import drafts.'
            );
        }
    }
}
