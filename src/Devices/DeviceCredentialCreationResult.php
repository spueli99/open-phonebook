<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

final readonly class DeviceCredentialCreationResult
{
    public function __construct(
        public Device $device,
        public DeviceCredentialScope $scope,
        public string $plainTextToken,
    ) {
    }
}
