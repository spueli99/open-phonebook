<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

final readonly class AuthenticatedDevice
{
    public function __construct(
        public int $credentialId,
        public int $deviceId,
        public int $ownerUserId,
        public DeviceCredentialScope $scope,
    ) {
    }
}
