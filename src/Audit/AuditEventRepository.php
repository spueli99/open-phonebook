<?php

declare(strict_types=1);

namespace OpenPhonebook\Audit;

use DateTimeImmutable;
use JsonException;
use PDO;
use RuntimeException;

final class AuditEventRepository implements AuditEventWriterInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function record(
        AuditEventType $type,
        int $actorUserId,
        AuditTargetType $targetType,
        int $targetId,
        array $metadata = []
    ): void {
        $metadata = AuditMetadata::validate($metadata);

        try {
            $metadataJson = json_encode(
                $metadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Audit metadata could not be encoded.',
                0,
                $exception
            );
        }

        if (strlen($metadataJson) > 2048) {
            throw new RuntimeException('Audit metadata is too large.');
        }

        $statement = $this->database->prepare(
            'INSERT INTO audit_events (
                event_type,
                actor_user_id,
                target_type,
                target_id,
                metadata_json,
                created_at
            ) VALUES (
                :event_type,
                :actor_user_id,
                :target_type,
                :target_id,
                :metadata_json,
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'event_type' => $type->value,
            'actor_user_id' => $actorUserId,
            'target_type' => $targetType->value,
            'target_id' => $targetId,
            'metadata_json' => $metadataJson,
        ]);
    }

    /**
     * @return list<AuditEvent>
     */
    public function latest(int $limit = 100): array
    {
        if ($limit < 1 || $limit > 500) {
            throw new RuntimeException('Audit event limit is invalid.');
        }

        $statement = $this->database->prepare(
            'SELECT
                id,
                event_type,
                actor_user_id,
                target_type,
                target_id,
                metadata_json,
                created_at
            FROM audit_events
            ORDER BY id DESC
            LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $events = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $metadata = json_decode(
                (string) $row['metadata_json'],
                true,
                16,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($metadata)) {
                throw new RuntimeException('Stored audit metadata is invalid.');
            }

            /** @var array<string, bool|int|string> $metadata */
            $events[] = new AuditEvent(
                (int) $row['id'],
                AuditEventType::from((string) $row['event_type']),
                $row['actor_user_id'] === null
                    ? null
                    : (int) $row['actor_user_id'],
                AuditTargetType::from((string) $row['target_type']),
                (int) $row['target_id'],
                $metadata,
                new DateTimeImmutable((string) $row['created_at'])
            );
        }

        return $events;
    }
}
