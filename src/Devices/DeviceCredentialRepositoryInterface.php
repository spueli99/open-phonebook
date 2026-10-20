<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

use DateTimeImmutable;

interface DeviceCredentialRepositoryInterface
{
    public function create(
        int $deviceId,
        DeviceCredentialScope $scope,
        string $tokenHash
    ): void;

    public function revokeActive(
        int $deviceId,
        DeviceCredentialScope $scope
    ): void;

    public function hasActive(
        int $deviceId,
        DeviceCredentialScope $scope
    ): bool;

    public function findActiveByTokenHash(
        string $tokenHash,
        DeviceCredentialScope $scope
    ): ?AuthenticatedDevice;

    public function markLastUsed(int $credentialId): void;

    public function activeLastUsedAt(
        int $deviceId,
        DeviceCredentialScope $scope
    ): ?DateTimeImmutable;
}
