<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

final class DeviceCredentialHttpAuthenticator
{
    private const BASIC_USERNAME = 'device';

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

        if (is_string($authorization) && $authorization !== '') {
            $token = $this->tokenFromAuthorization($authorization);

            return $token === null
                ? null
                : $this->authenticator->authenticate($token, $scope);
        }

        $username = $server['PHP_AUTH_USER'] ?? null;
        $password = $server['PHP_AUTH_PW'] ?? null;

        if (
            $username !== self::BASIC_USERNAME
            || !is_string($password)
        ) {
            return null;
        }

        return $this->authenticator->authenticate(
            $password,
            $scope
        );
    }

    /**
     * @param array<string, mixed> $server
     */
    public function authenticateBasic(
        array $server,
        DeviceCredentialScope $scope
    ): ?AuthenticatedDevice {
        $authorization = $server['HTTP_AUTHORIZATION']
            ?? $server['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if (is_string($authorization) && $authorization !== '') {
            $token = $this->basicTokenFromAuthorization($authorization);

            return $token === null
                ? null
                : $this->authenticator->authenticate($token, $scope);
        }

        $username = $server['PHP_AUTH_USER'] ?? null;
        $password = $server['PHP_AUTH_PW'] ?? null;

        if (
            $username !== self::BASIC_USERNAME
            || !is_string($password)
            || preg_match('/^[a-f0-9]{64}$/', $password) !== 1
        ) {
            return null;
        }

        return $this->authenticator->authenticate(
            $password,
            $scope
        );
    }

    public function markUsed(AuthenticatedDevice $device): void
    {
        $this->authenticator->markUsed($device);
    }

    private function tokenFromAuthorization(string $value): ?string
    {
        if (
            preg_match(
                '/^Bearer ([a-f0-9]{64})$/',
                $value,
                $matches
            ) === 1
        ) {
            return $matches[1];
        }

        return $this->basicTokenFromAuthorization($value);
    }

    private function basicTokenFromAuthorization(
        string $value
    ): ?string {
        if (
            preg_match(
                '/^Basic ([A-Za-z0-9+\/=]+)$/',
                $value,
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

        if ($username !== self::BASIC_USERNAME) {
            return null;
        }

        return preg_match('/^[a-f0-9]{64}$/', $token) === 1
            ? $token
            : null;
    }
}
