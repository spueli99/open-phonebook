<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Notifications;

use OpenPhonebook\Notifications\NotificationRule;
use OpenPhonebook\Notifications\NotificationRuleRepositoryInterface;
use OpenPhonebook\Notifications\NotificationTrigger;

final class InMemoryNotificationRuleRepository implements NotificationRuleRepositoryInterface
{
    /** @var array<int, NotificationRule> */
    private array $rules = [];

    private int $nextId = 1;

    public function allOwnedBy(int $ownerUserId): array
    {
        return array_values(
            array_filter(
                $this->rules,
                static fn (NotificationRule $rule): bool =>
                    $rule->ownerUserId === $ownerUserId
            )
        );
    }

    public function matching(
        int $ownerUserId,
        int $deviceId,
        NotificationTrigger $trigger
    ): array {
        return array_values(
            array_filter(
                $this->rules,
                static fn (NotificationRule $rule): bool =>
                    $rule->ownerUserId === $ownerUserId
                    && $rule->isActive
                    && $rule->trigger === $trigger
                    && (
                        $rule->deviceId === null
                        || $rule->deviceId === $deviceId
                    )
            )
        );
    }

    public function exists(
        int $ownerUserId,
        int $channelId,
        ?int $deviceId,
        NotificationTrigger $trigger
    ): bool {
        foreach ($this->rules as $rule) {
            if (
                $rule->ownerUserId === $ownerUserId
                && $rule->channelId === $channelId
                && $rule->deviceId === $deviceId
                && $rule->trigger === $trigger
            ) {
                return true;
            }
        }

        return false;
    }

    public function create(
        int $ownerUserId,
        int $channelId,
        ?int $deviceId,
        NotificationTrigger $trigger
    ): int {
        $id = $this->nextId++;
        $this->rules[$id] = new NotificationRule(
            $id,
            $ownerUserId,
            $channelId,
            $deviceId,
            $trigger,
            true
        );

        return $id;
    }

    public function delete(
        int $ruleId,
        int $ownerUserId
    ): bool {
        $rule = $this->rules[$ruleId] ?? null;

        if (
            $rule === null
            || $rule->ownerUserId !== $ownerUserId
        ) {
            return false;
        }

        unset($this->rules[$ruleId]);

        return true;
    }
}
