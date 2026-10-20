<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Audit;

use OpenPhonebook\Audit\AuditEventType;
use OpenPhonebook\Audit\AuditEventWriterInterface;
use OpenPhonebook\Audit\AuditTargetType;

final class RecordingAuditEventWriter implements AuditEventWriterInterface
{
    /**
     * @var list<array{
     *     type: AuditEventType,
     *     actor_user_id: int,
     *     target_type: AuditTargetType,
     *     target_id: int,
     *     metadata: array<string, bool|int|string>
     * }>
     */
    public array $events = [];

    public function record(
        AuditEventType $type,
        int $actorUserId,
        AuditTargetType $targetType,
        int $targetId,
        array $metadata = []
    ): void {
        $this->events[] = [
            'type' => $type,
            'actor_user_id' => $actorUserId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata,
        ];
    }
}
