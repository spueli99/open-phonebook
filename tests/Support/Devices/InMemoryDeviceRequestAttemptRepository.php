<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Devices;

use DateTimeImmutable;
use OpenPhonebook\Devices\DeviceRequestAttempt;
use OpenPhonebook\Devices\DeviceRequestAttemptRepositoryInterface;

final class InMemoryDeviceRequestAttemptRepository implements
    DeviceRequestAttemptRepositoryInterface
{
    /** @var array<string, DeviceRequestAttempt> */
    private array $attempts = [];

    public function find(string $subjectHash): ?DeviceRequestAttempt
    {
        return $this->attempts[$subjectHash] ?? null;
    }

    public function save(
        string $subjectHash,
        DeviceRequestAttempt $attempt
    ): void {
        $this->attempts[$subjectHash] = $attempt;
    }

    public function delete(string $subjectHash): void
    {
        unset($this->attempts[$subjectHash]);
    }

    public function deleteUpdatedBefore(DateTimeImmutable $cutoff): void
    {
        // The in-memory test repository has no separate updated-at clock.
    }

    /**
     * @return array<string, DeviceRequestAttempt>
     */
    public function all(): array
    {
        return $this->attempts;
    }
}
