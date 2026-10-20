<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

interface NotificationChannelRepositoryInterface
{
    /**
     * @return list<NotificationChannel>
     */
    public function allOwnedBy(int $ownerUserId): array;

    public function findOwnedBy(
        int $channelId,
        int $ownerUserId
    ): ?NotificationChannel;

    public function create(
        int $ownerUserId,
        string $name,
        string $provider,
        string $encryptedConfig
    ): int;

    public function updateConfig(
        int $channelId,
        int $ownerUserId,
        string $encryptedConfig
    ): bool;

    public function setActive(
        int $channelId,
        int $ownerUserId,
        bool $active
    ): bool;

    public function delete(
        int $channelId,
        int $ownerUserId
    ): bool;
}
