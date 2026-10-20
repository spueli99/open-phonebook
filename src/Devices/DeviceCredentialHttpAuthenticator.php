<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

final class DeviceCredentialHttpAuthenticator
{
    public function __construct(
        private readonly DeviceCredentialAuthenticator $authenticator
    ) {
    }

    /**
     * @param array<string, mixed> $server
     */
    public function authenticate(
        array $server,
        DeviceCredentialScope $scope
    ): ?AuthenticatedDevice {
        $authorization = $server['HTTP_AUTHORIZATION']
            ?? $server['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if (!is_string($authorization) || $authorization === '') {
            return null;
        }

        $token = $this->bearerToken($authorization)
            ?? $this->basicToken($authorization);

        return $token === null
            ? null
            : $this->authenticator->authenticate($token, $scope);
    }

    private function bearerToken(string $authorization): ?string
    {
        if (
            preg_match(
                '/^Bearer ([a-f0-9]{64})$/',
                $authorization,
                $matches
            ) !== 1
        ) {
            return null;
        }

        return $matches[1];
    }

    private function basicToken(string $authorization): ?string
    {
        if (
            preg_match(
                '/^Basic ([A-Za-z0-9+\/=]+)$/',
                $authorization,
                $matches
            ) !== 1
        ) {
            return null;
        }

        $decoded = base64_decode($matches[1], true);

        if ($decoded === false || !str_contains($decoded, ':')) {
            return null;
        }

        [$username, $token] = explode(':', $decoded, 2);

        if ($username !== 'device') {
            return null;
        }

        return preg_match('/^[a-f0-9]{64}$/', $token) === 1
            ? $token
            : null;
    }
}
