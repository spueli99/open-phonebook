<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

use DateInterval;
use OpenPhonebook\Clock\ClockInterface;

final class DeviceRequestThrottle
{
    private const MAX_FAILURES = 20;
    private const WINDOW_MINUTES = 15;
    private const BLOCK_MINUTES = 15;
    private const RETENTION_HOURS = 24;

    public function __construct(
        private readonly DeviceRequestAttemptRepositoryInterface $attempts,
        private readonly string $applicationKey,
        private readonly ClockInterface $clock,
    ) {
    }

    public function isBlocked(
        string $endpoint,
        string $ipAddress
    ): bool {
        $attempt = $this->attempts->find(
            $this->subjectHash($endpoint, $ipAddress)
        );

        return $attempt?->blockedUntil !== null
            && $this->clock->now() < $attempt->blockedUntil;
    }

    public function registerFailure(
        string $endpoint,
        string $ipAddress
    ): void {
        $now = $this->clock->now();
        $this->attempts->deleteUpdatedBefore(
            $now->sub(
                new DateInterval('PT' . self::RETENTION_HOURS . 'H')
            )
        );

        $subjectHash = $this->subjectHash(
            $endpoint,
            $ipAddress
        );
        $current = $this->attempts->find($subjectHash);
        $failedAttempts = 1;
        $windowStartedAt = $now;

        if (
            $current !== null
            && $current->windowStartedAt->add(
                new DateInterval(
                    'PT' . self::WINDOW_MINUTES . 'M'
                )
            ) > $now
        ) {
            $failedAttempts = $current->failedAttempts + 1;
            $windowStartedAt = $current->windowStartedAt;
        }

        $blockedUntil = null;

        if ($failedAttempts >= self::MAX_FAILURES) {
            $blockedUntil = $now->add(
                new DateInterval(
                    'PT' . self::BLOCK_MINUTES . 'M'
                )
            );
        }

        $this->attempts->save(
            $subjectHash,
            new DeviceRequestAttempt(
                $failedAttempts,
                $windowStartedAt,
                $blockedUntil
            )
        );
    }

    public function clear(
        string $endpoint,
        string $ipAddress
    ): void {
        $this->attempts->delete(
            $this->subjectHash($endpoint, $ipAddress)
        );
    }

    private function subjectHash(
        string $endpoint,
        string $ipAddress
    ): string {
        return hash_hmac(
            'sha256',
            'open-phonebook/device-request-throttle/v1'
                . '|' . $endpoint
                . '|' . $ipAddress,
            $this->applicationKey
        );
    }
}
