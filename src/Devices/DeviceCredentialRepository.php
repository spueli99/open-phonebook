<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

use PDO;

final class DeviceCredentialRepository implements DeviceCredentialRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function create(
        int $deviceId,
        DeviceCredentialScope $scope,
        string $tokenHash
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO device_credentials (
                device_id,
                scope,
                token_hash,
                created_at
             ) VALUES (
                :device_id,
                :scope,
                :token_hash,
                UTC_TIMESTAMP()
             )'
        );
        $statement->execute([
            'device_id' => $deviceId,
            'scope' => $scope->value,
            'token_hash' => $tokenHash,
        ]);
    }

    public function revokeActive(
        int $deviceId,
        DeviceCredentialScope $scope
    ): void {
        $statement = $this->database->prepare(
            'UPDATE device_credentials
             SET revoked_at = UTC_TIMESTAMP()
             WHERE device_id = :device_id
               AND scope = :scope
               AND revoked_at IS NULL'
        );
        $statement->execute([
            'device_id' => $deviceId,
            'scope' => $scope->value,
        ]);
    }

    public function hasActive(
        int $deviceId,
        DeviceCredentialScope $scope
    ): bool {
        $statement = $this->database->prepare(
            'SELECT 1
             FROM device_credentials
             WHERE device_id = :device_id
               AND scope = :scope
               AND revoked_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'device_id' => $deviceId,
            'scope' => $scope->value,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
