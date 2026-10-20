<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class CallHistoryRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function recentOwnedBy(
        int $ownerUserId,
        ?int $beforeId = null,
        int $limit = 50
    ): CallHistoryPage {
        $limit = max(1, min(100, $limit));
        $fetchLimit = $limit + 1;
        $beforeClause = $beforeId === null
            ? ''
            : ' AND s.id < :before_id';

        $statement = $this->database->prepare(
            'SELECT s.id, d.name AS device_name, s.direction, s.state,
                    s.remote_number, s.contact_name, s.started_at, s.ended_at
             FROM call_sessions s
             INNER JOIN devices d ON d.id = s.device_id
             WHERE d.owner_user_id = :owner_user_id'
            . $beforeClause
            . ' ORDER BY s.id DESC
                LIMIT ' . $fetchLimit
        );
        $parameters = ['owner_user_id' => $ownerUserId];

        if ($beforeId !== null) {
            $parameters['before_id'] = $beforeId;
        }

        $statement->execute($parameters);
        $items = [];

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException(
                    'Unexpected call history query result.'
                );
            }

            $items[] = new CallHistoryItem(
                (int) $row['id'],
                (string) $row['device_name'],
                CallDirection::from((string) $row['direction']),
                CallSessionState::from((string) $row['state']),
                $row['remote_number'] === null
                    ? null
                    : (string) $row['remote_number'],
                $row['contact_name'] === null
                    ? null
                    : (string) $row['contact_name'],
                self::parseUtc((string) $row['started_at']),
                $row['ended_at'] === null
                    ? null
                    : self::parseUtc((string) $row['ended_at'])
            );
        }

        $hasMore = count($items) > $limit;

        if ($hasMore) {
            array_pop($items);
        }

        $olderBeforeId = $hasMore
            ? $items[array_key_last($items)]->id
            : null;

        return new CallHistoryPage($items, $olderBeforeId);
    }

    private static function parseUtc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $value,
            new DateTimeZone('UTC')
        );
    }
}
