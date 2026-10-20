<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use PDO;
use RuntimeException;

final class NotificationChannelRepository implements NotificationChannelRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function allOwnedBy(int $ownerUserId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, owner_user_id, name, provider,
                    encrypted_config, is_active
             FROM notification_channels
             WHERE owner_user_id = :owner_user_id
             ORDER BY name ASC, id ASC'
        );
        $statement->execute(['owner_user_id' => $ownerUserId]);

        $channels = [];

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException(
                    'Unexpected notification channel query result.'
                );
            }

            $channels[] = $this->hydrate($row);
        }

        return $channels;
    }

    public function findOwnedBy(
        int $channelId,
        int $ownerUserId
    ): ?NotificationChannel {
        $statement = $this->database->prepare(
            'SELECT id, owner_user_id, name, provider,
                    encrypted_config, is_active
             FROM notification_channels
             WHERE id = :id
               AND owner_user_id = :owner_user_id
             LIMIT 1'
        );
        $statement->execute([
            'id' => $channelId,
            'owner_user_id' => $ownerUserId,
        ]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException(
                'Unexpected notification channel query result.'
            );
        }

        return $this->hydrate($row);
    }

    public function create(
        int $ownerUserId,
        string $name,
        string $provider,
        string $encryptedConfig
    ): int {
        $statement = $this->database->prepare(
            'INSERT INTO notification_channels (
                owner_user_id,
                name,
                provider,
                encrypted_config,
                is_active,
                created_at,
                updated_at
             ) VALUES (
                :owner_user_id,
                :name,
                :provider,
                :encrypted_config,
                1,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )'
        );
        $statement->execute([
            'owner_user_id' => $ownerUserId,
            'name' => $name,
            'provider' => $provider,
            'encrypted_config' => $encryptedConfig,
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function updateConfig(
        int $channelId,
        int $ownerUserId,
        string $encryptedConfig
    ): bool {
        $statement = $this->database->prepare(
            'UPDATE notification_channels
             SET encrypted_config = :encrypted_config,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND owner_user_id = :owner_user_id'
        );
        $statement->execute([
            'encrypted_config' => $encryptedConfig,
            'id' => $channelId,
            'owner_user_id' => $ownerUserId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function setActive(
        int $channelId,
        int $ownerUserId,
        bool $active
    ): bool {
        $statement = $this->database->prepare(
            'UPDATE notification_channels
             SET is_active = :active,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id
               AND owner_user_id = :owner_user_id'
        );
        $statement->execute([
            'active' => $active ? 1 : 0,
            'id' => $channelId,
            'owner_user_id' => $ownerUserId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function delete(
        int $channelId,
        int $ownerUserId
    ): bool {
        $statement = $this->database->prepare(
            'DELETE FROM notification_channels
             WHERE id = :id
               AND owner_user_id = :owner_user_id'
        );
        $statement->execute([
            'id' => $channelId,
            'owner_user_id' => $ownerUserId,
        ]);

        return $statement->rowCount() === 1;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): NotificationChannel
    {
        return new NotificationChannel(
            (int) $row['id'],
            (int) $row['owner_user_id'],
            (string) $row['name'],
            (string) $row['provider'],
            (string) $row['encrypted_config'],
            (bool) $row['is_active'],
        );
    }
}
