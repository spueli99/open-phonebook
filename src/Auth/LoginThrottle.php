<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class LoginThrottle
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;
    private const BLOCK_MINUTES = 15;

    public function __construct(
        private readonly PDO $database,
        private readonly string $applicationKey,
    ) {
    }

    public function isBlocked(string $email, string $ipAddress): bool
    {
        $row = $this->find($this->subjectHash($email, $ipAddress));

        if ($row === null || $row['blocked_until'] === null) {
            return false;
        }

        return $this->utcNow() < new DateTimeImmutable(
            (string) $row['blocked_until'],
            new DateTimeZone('UTC')
        );
    }

    public function registerFailure(string $email, string $ipAddress): void
    {
        $subjectHash = $this->subjectHash($email, $ipAddress);
        $now = $this->utcNow();
        $row = $this->find($subjectHash);

        $attempts = 1;
        $windowStarted = $now;

        if ($row !== null) {
            $storedWindow = new DateTimeImmutable(
                (string) $row['window_started_at'],
                new DateTimeZone('UTC')
            );

            if ($storedWindow->add(new DateInterval('PT' . self::WINDOW_MINUTES . 'M')) > $now) {
                $attempts = (int) $row['failed_attempts'] + 1;
                $windowStarted = $storedWindow;
            }
        }

        $blockedUntil = null;
        if ($attempts >= self::MAX_ATTEMPTS) {
            $blockedUntil = $now->add(
                new DateInterval('PT' . self::BLOCK_MINUTES . 'M')
            );
        }

        $statement = $this->database->prepare(
            'INSERT INTO auth_login_attempts (
                subject_hash,
                failed_attempts,
                window_started_at,
                blocked_until,
                updated_at
            ) VALUES (
                :subject_hash,
                :failed_attempts,
                :window_started_at,
                :blocked_until,
                UTC_TIMESTAMP()
            )
            ON DUPLICATE KEY UPDATE
                failed_attempts = VALUES(failed_attempts),
                window_started_at = VALUES(window_started_at),
                blocked_until = VALUES(blocked_until),
                updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            'subject_hash' => $subjectHash,
            'failed_attempts' => $attempts,
            'window_started_at' => $windowStarted->format('Y-m-d H:i:s'),
            'blocked_until' => $blockedUntil?->format('Y-m-d H:i:s'),
        ]);
    }

    public function clear(string $email, string $ipAddress): void
    {
        $statement = $this->database->prepare(
            'DELETE FROM auth_login_attempts WHERE subject_hash = :subject_hash'
        );
        $statement->execute([
            'subject_hash' => $this->subjectHash($email, $ipAddress),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find(string $subjectHash): ?array
    {
        $statement = $this->database->prepare(
            'SELECT failed_attempts, window_started_at, blocked_until
             FROM auth_login_attempts
             WHERE subject_hash = :subject_hash
             LIMIT 1'
        );
        $statement->execute(['subject_hash' => $subjectHash]);

        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function subjectHash(string $email, string $ipAddress): string
    {
        return hash_hmac(
            'sha256',
            strtolower(trim($email)) . '|' . $ipAddress,
            $this->applicationKey
        );
    }

    private function utcNow(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
