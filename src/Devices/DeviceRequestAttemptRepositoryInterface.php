<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

use DateTimeImmutable;

interface DeviceRequestAttemptRepositoryInterface
{
    public function find(string $subjectHash): ?DeviceRequestAttempt;

    public function save(
        string $subjectHash,
        DeviceRequestAttempt $attempt
    ): void;

    public function delete(string $subjectHash): void;

    public function deleteUpdatedBefore(DateTimeImmutable $cutoff): void;
}
