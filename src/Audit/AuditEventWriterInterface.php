<?php

declare(strict_types=1);

namespace OpenPhonebook\Audit;

interface AuditEventWriterInterface
{
    /**
     * @param array<string, bool|int|string> $metadata
     */
    public function record(
        AuditEventType $type,
        int $actorUserId,
        AuditTargetType $targetType,
        int $targetId,
        array $metadata = []
    ): void;
}
