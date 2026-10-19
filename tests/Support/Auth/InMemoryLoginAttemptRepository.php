<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Auth;

use OpenPhonebook\Auth\LoginAttempt;
use OpenPhonebook\Auth\LoginAttemptRepositoryInterface;
use RuntimeException;

final class InMemoryLoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    /**
     * @var array<string, LoginAttempt>
     */
    private array $attempts = [];

    public function find(string $subjectHash): ?LoginAttempt
    {
        return $this->attempts[$subjectHash] ?? null;
    }

    public function save(string $subjectHash, LoginAttempt $attempt): void
    {
        $this->attempts[$subjectHash] = $attempt;
    }

    public function delete(string $subjectHash): void
    {
        unset($this->attempts[$subjectHash]);
    }

    /**
     * @return array<string, LoginAttempt>
     */
    public function all(): array
    {
        return $this->attempts;
    }

    public function onlyAttempt(): LoginAttempt
    {
        if (count($this->attempts) !== 1) {
            throw new RuntimeException('Expected exactly one login attempt.');
        }

        return array_values($this->attempts)[0];
    }
}
