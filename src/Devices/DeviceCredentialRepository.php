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

    public function findActiveByTokenHash(
        string $tokenHash,
        DeviceCredentialScope $scope
    ): ?AuthenticatedDevice {
        $statement = $this->database->prepare(
            'SELECT d.id AS device_id, d.owner_user_id
             FROM device_credentials c
             INNER JOIN devices d ON d.id = c.device_id
             INNER JOIN users u ON u.id = d.owner_user_id
             WHERE c.token_hash = :token_hash
               AND c.scope = :scope
               AND c.revoked_at IS NULL
               AND d.is_active = 1
               AND u.is_active = 1
               AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'token_hash' => $tokenHash,
            'scope' => $scope->value,
        ]);

        $row = $statement->fetch();

        if ($row === false || !is_array($row)) {
            return null;
        }

        return new AuthenticatedDevice(
            (int) $row['device_id'],
            (int) $row['owner_user_id'],
            $scope,
        );
    }
}
