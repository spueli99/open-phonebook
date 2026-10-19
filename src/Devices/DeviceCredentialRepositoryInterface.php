<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

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
}
