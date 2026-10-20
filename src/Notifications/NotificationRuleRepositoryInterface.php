<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

interface NotificationRuleRepositoryInterface
{
    /**
     * @return list<NotificationRule>
     */
    public function allOwnedBy(int $ownerUserId): array;

    /**
     * @return list<NotificationRule>
     */
    public function matching(
        int $ownerUserId,
        int $deviceId,
        NotificationTrigger $trigger
    ): array;

    public function exists(
        int $ownerUserId,
        int $channelId,
        ?int $deviceId,
        NotificationTrigger $trigger
    ): bool;

    public function create(
        int $ownerUserId,
        int $channelId,
        ?int $deviceId,
        NotificationTrigger $trigger
    ): int;

    public function delete(
        int $ruleId,
        int $ownerUserId
    ): bool;
}
