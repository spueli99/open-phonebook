<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Auth;

use DateTimeImmutable;
use OpenPhonebook\Auth\PasswordResetRepositoryInterface;
use OpenPhonebook\Auth\PasswordResetRequest;
use RuntimeException;

final class InMemoryPasswordResetRepository implements PasswordResetRepositoryInterface
{
    /** @var array<int, array{request: PasswordResetRequest, token_hash: string, requested_at: DateTimeImmutable}> */
    private array $requests = [];

    private int $nextId = 1;

    public function hasRecentRequest(
        int $userId,
        DateTimeImmutable $since
    ): bool {
        foreach ($this->requests as $item) {
            if (
                $item['request']->userId === $userId
                && $item['requested_at'] >= $since
            ) {
                return true;
            }
        }

        return false;
    }

    public function invalidateUsableForUser(
        int $userId,
        DateTimeImmutable $now
    ): void {
        foreach ($this->requests as $id => $item) {
            if (
                $item['request']->userId !== $userId
                || !$item['request']->isUsable($now)
            ) {
                continue;
            }

            $this->requests[$id]['request'] =
                new PasswordResetRequest(
                    $item['request']->id,
                    $item['request']->userId,
                    $item['request']->expiresAt,
                    $now
                );
        }
    }

    public function create(
        int $userId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $requestedAt
    ): int {
        $id = $this->nextId++;
        $this->requests[$id] = [
            'request' => new PasswordResetRequest(
                $id,
                $userId,
                $expiresAt,
                null
            ),
            'token_hash' => $tokenHash,
            'requested_at' => $requestedAt,
        ];

        return $id;
    }

    public function findUsableByTokenHash(
        string $tokenHash,
        DateTimeImmutable $now
    ): ?PasswordResetRequest {
        foreach ($this->requests as $item) {
            if (
                hash_equals($item['token_hash'], $tokenHash)
                && $item['request']->isUsable($now)
            ) {
                return $item['request'];
            }
        }

        return null;
    }

    public function markUsed(int $requestId): void
    {
        $item = $this->requests[$requestId] ?? null;

        if ($item === null || $item['request']->usedAt !== null) {
            throw new RuntimeException(
                'Password reset is no longer usable.'
            );
        }

        $this->requests[$requestId]['request'] =
            new PasswordResetRequest(
                $item['request']->id,
                $item['request']->userId,
                $item['request']->expiresAt,
                new DateTimeImmutable('now')
            );
    }

    public function count(): int
    {
        return count($this->requests);
    }

    public function usedCount(): int
    {
        $count = 0;

        foreach ($this->requests as $item) {
            if ($item['request']->usedAt !== null) {
                $count++;
            }
        }

        return $count;
    }
}
