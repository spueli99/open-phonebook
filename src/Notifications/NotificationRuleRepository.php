<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use PDO;
use PDOStatement;
use RuntimeException;

final class NotificationRuleRepository implements NotificationRuleRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function allOwnedBy(int $ownerUserId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, owner_user_id, channel_id, device_id,
                    trigger_name, is_active
             FROM notification_rules
             WHERE owner_user_id = :owner_user_id
             ORDER BY id ASC'
        );

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException(
                'Notification rule query could not be prepared.'
            );
        }

        $statement->execute(['owner_user_id' => $ownerUserId]);

        return $this->fetchAll($statement);
    }

    public function matching(
        int $ownerUserId,
        int $deviceId,
        NotificationTrigger $trigger
    ): array {
        $statement = $this->database->prepare(
            'SELECT id, owner_user_id, channel_id, device_id,
                    trigger_name, is_active
             FROM notification_rules
             WHERE owner_user_id = :owner_user_id
               AND is_active = 1
               AND trigger_name = :trigger_name
               AND (device_id IS NULL OR device_id = :device_id)
             ORDER BY id ASC'
        );

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException(
                'Notification rule query could not be prepared.'
            );
        }

        $statement->execute([
            'owner_user_id' => $ownerUserId,
            'trigger_name' => $trigger->value,
            'device_id' => $deviceId,
        ]);

        return $this->fetchAll($statement);
    }

    public function exists(
        int $ownerUserId,
        int $channelId,
        ?int $deviceId,
        NotificationTrigger $trigger
    ): bool {
        $sql = 'SELECT 1
                FROM notification_rules
                WHERE owner_user_id = :owner_user_id
                  AND channel_id = :channel_id
                  AND trigger_name = :trigger_name
                  AND ' . ($deviceId === null
                    ? 'device_id IS NULL'
                    : 'device_id = :device_id')
            . ' LIMIT 1';
        $statement = $this->database->prepare($sql);
        $parameters = [
            'owner_user_id' => $ownerUserId,
            'channel_id' => $channelId,
            'trigger_name' => $trigger->value,
        ];

        if ($deviceId !== null) {
            $parameters['device_id'] = $deviceId;
        }

        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    public function create(
        int $ownerUserId,
        int $channelId,
        ?int $deviceId,
        NotificationTrigger $trigger
    ): int {
        $statement = $this->database->prepare(
            'INSERT INTO notification_rules (
                owner_user_id,
                channel_id,
                device_id,
                trigger_name,
                is_active,
                created_at,
                updated_at
             ) VALUES (
                :owner_user_id,
                :channel_id,
                :device_id,
                :trigger_name,
                1,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )'
        );
        $statement->execute([
            'owner_user_id' => $ownerUserId,
            'channel_id' => $channelId,
            'device_id' => $deviceId,
            'trigger_name' => $trigger->value,
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function delete(
        int $ruleId,
        int $ownerUserId
    ): bool {
        $statement = $this->database->prepare(
            'DELETE FROM notification_rules
             WHERE id = :id
               AND owner_user_id = :owner_user_id'
        );
        $statement->execute([
            'id' => $ruleId,
            'owner_user_id' => $ownerUserId,
        ]);

        return $statement->rowCount() === 1;
    }

    /**
     * @return list<NotificationRule>
     */
    private function fetchAll(PDOStatement $statement): array
    {
        $rules = [];

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException(
                    'Unexpected notification rule query result.'
                );
            }

            $rules[] = new NotificationRule(
                (int) $row['id'],
                (int) $row['owner_user_id'],
                (int) $row['channel_id'],
                $row['device_id'] === null
                    ? null
                    : (int) $row['device_id'],
                NotificationTrigger::from(
                    (string) $row['trigger_name']
                ),
                (bool) $row['is_active'],
            );
        }

        return $rules;
    }
}
