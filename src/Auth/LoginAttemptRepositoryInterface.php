<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

interface LoginAttemptRepositoryInterface
{
    public function find(string $subjectHash): ?LoginAttempt;

    public function save(string $subjectHash, LoginAttempt $attempt): void;

    public function delete(string $subjectHash): void;
}
