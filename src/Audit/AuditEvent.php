<?php

declare(strict_types=1);

namespace OpenPhonebook\Audit;

use DateTimeImmutable;

final readonly class AuditEvent
{
    /**
     * @param array<string, bool|int|string> $metadata
     */
    public function __construct(
        public int $id,
        public AuditEventType $type,
        public ?int $actorUserId,
        public AuditTargetType $targetType,
        public int $targetId,
        public array $metadata,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
