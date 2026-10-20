<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

final class DeviceCredentialAuthenticator
{
    public function __construct(
        private readonly DeviceCredentialRepositoryInterface $credentials,
        private readonly string $applicationKey,
    ) {
    }

    public function authenticate(
        string $plainTextToken,
        DeviceCredentialScope $scope
    ): ?AuthenticatedDevice {
        if (!preg_match('/^[a-f0-9]{64}$/', $plainTextToken)) {
            return null;
        }

        return $this->credentials->findActiveByTokenHash(
            DeviceCredentialToken::hash(
                $plainTextToken,
                $this->applicationKey
            ),
            $scope
        );
    }

    public function markUsed(AuthenticatedDevice $device): void
    {
        $this->credentials->markLastUsed($device->credentialId);
    }
}
