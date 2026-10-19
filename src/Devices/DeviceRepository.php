<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

use PDO;
use RuntimeException;

final class DeviceRepository implements DeviceRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function allOwnedBy(int $ownerUserId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, owner_user_id, name, vendor, model, is_active
             FROM devices
             WHERE owner_user_id = :owner_user_id
             ORDER BY name ASC, id ASC'
        );
        $statement->execute(['owner_user_id' => $ownerUserId]);

        $devices = [];

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException(
                    'Unexpected device query result.'
                );
            }

            $devices[] = $this->hydrate($row);
        }

        return $devices;
    }

    public function findOwnedBy(
        int $deviceId,
        int $ownerUserId
    ): ?Device {
        $statement = $this->database->prepare(
            'SELECT id, owner_user_id, name, vendor, model, is_active
             FROM devices
             WHERE id = :id
               AND owner_user_id = :owner_user_id
             LIMIT 1'
        );
        $statement->execute([
            'id' => $deviceId,
            'owner_user_id' => $ownerUserId,
        ]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException(
                'Unexpected device query result.'
            );
        }

        return $this->hydrate($row);
    }

    public function findOwnedByForUpdate(
        int $deviceId,
        int $ownerUserId
    ): ?Device {
        $statement = $this->database->prepare(
            'SELECT id, owner_user_id, name, vendor, model, is_active
             FROM devices
             WHERE id = :id
               AND owner_user_id = :owner_user_id
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute([
            'id' => $deviceId,
            'owner_user_id' => $ownerUserId,
        ]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException(
                'Unexpected device query result.'
            );
        }

        return $this->hydrate($row);
    }

    public function create(
        int $ownerUserId,
        string $name,
        ?string $vendor,
        ?string $model
    ): int {
        $statement = $this->database->prepare(
            'INSERT INTO devices (
                owner_user_id,
                name,
                vendor,
                model,
                is_active,
                created_at,
                updated_at
             ) VALUES (
                :owner_user_id,
                :name,
                :vendor,
                :model,
                1,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )'
        );
        $statement->execute([
            'owner_user_id' => $ownerUserId,
            'name' => $name,
            'vendor' => $vendor,
            'model' => $model,
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function setActive(
        int $deviceId,
        int $ownerUserId,
        bool $active
    ): bool {
        $statement = $this->database->prepare(
            'UPDATE devices
             SET is_active = :is_active,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND owner_user_id = :owner_user_id'
        );
        $statement->execute([
            'is_active' => $active ? 1 : 0,
            'id' => $deviceId,
            'owner_user_id' => $ownerUserId,
        ]);

        return $statement->rowCount() === 1;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Device
    {
        return new Device(
            (int) $row['id'],
            (int) $row['owner_user_id'],
            (string) $row['name'],
            $row['vendor'] === null ? null : (string) $row['vendor'],
            $row['model'] === null ? null : (string) $row['model'],
            (bool) $row['is_active'],
        );
    }
}
