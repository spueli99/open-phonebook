<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use PDO;
use PDOStatement;
use RuntimeException;

final class UserTimezoneRepository implements UserTimezoneRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function timezoneForUser(int $userId): string
    {
        $statement = $this->database->prepare(
            'SELECT timezone
             FROM users
             WHERE id = :id
               AND is_active = 1
               AND deleted_at IS NULL
             LIMIT 1'
        );

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException(
                'User timezone query could not be prepared.'
            );
        }

        $statement->execute(['id' => $userId]);
        $timezone = $statement->fetchColumn();

        if (!is_string($timezone) || $timezone === '') {
            throw new RuntimeException('User timezone is unavailable.');
        }

        return $timezone;
    }
}
