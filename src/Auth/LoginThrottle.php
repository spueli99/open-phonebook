<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

use DateInterval;
use OpenPhonebook\Clock\ClockInterface;

final class LoginThrottle
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;
    private const BLOCK_MINUTES = 15;

    public function __construct(
        private readonly LoginAttemptRepositoryInterface $attempts,
        private readonly string $applicationKey,
        private readonly ClockInterface $clock,
    ) {
    }

    public function isBlocked(string $email, string $ipAddress): bool
    {
        $attempt = $this->attempts->find(
            $this->subjectHash($email, $ipAddress)
        );

        return $attempt?->blockedUntil !== null
            && $this->clock->now() < $attempt->blockedUntil;
    }

    public function registerFailure(string $email, string $ipAddress): void
    {
        $subjectHash = $this->subjectHash($email, $ipAddress);
        $now = $this->clock->now();
        $current = $this->attempts->find($subjectHash);

        $failedAttempts = 1;
        $windowStartedAt = $now;

        if (
            $current !== null
            && $current->windowStartedAt->add(
                new DateInterval('PT' . self::WINDOW_MINUTES . 'M')
            ) > $now
        ) {
            $failedAttempts = $current->failedAttempts + 1;
            $windowStartedAt = $current->windowStartedAt;
        }

        $blockedUntil = null;

        if ($failedAttempts >= self::MAX_ATTEMPTS) {
            $blockedUntil = $now->add(
                new DateInterval('PT' . self::BLOCK_MINUTES . 'M')
            );
        }

        $this->attempts->save(
            $subjectHash,
            new LoginAttempt(
                $failedAttempts,
                $windowStartedAt,
                $blockedUntil,
            )
        );
    }

    public function clear(string $email, string $ipAddress): void
    {
        $this->attempts->delete(
            $this->subjectHash($email, $ipAddress)
        );
    }

    private function subjectHash(string $email, string $ipAddress): string
    {
        return hash_hmac(
            'sha256',
            strtolower(trim($email)) . '|' . $ipAddress,
            $this->applicationKey
        );
    }
}
