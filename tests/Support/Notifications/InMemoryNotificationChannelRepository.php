<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Notifications;

use OpenPhonebook\Notifications\NotificationChannel;
use OpenPhonebook\Notifications\NotificationChannelRepositoryInterface;

final class InMemoryNotificationChannelRepository
    implements NotificationChannelRepositoryInterface
{
    /** @var array<int, NotificationChannel> */
    private array $channels = [];

    private int $nextId = 1;

    public function allOwnedBy(int $ownerUserId): array
    {
        return array_values(
            array_filter(
                $this->channels,
                static fn (NotificationChannel $channel): bool =>
                    $channel->ownerUserId === $ownerUserId
            )
        );
    }

    public function findOwnedBy(
        int $channelId,
        int $ownerUserId
    ): ?NotificationChannel {
        $channel = $this->channels[$channelId] ?? null;

        if (
            $channel === null
            || $channel->ownerUserId !== $ownerUserId
        ) {
            return null;
        }

        return $channel;
    }

    public function create(
        int $ownerUserId,
        string $name,
        string $provider,
        string $encryptedConfig
    ): int {
        $id = $this->nextId++;
        $this->channels[$id] = new NotificationChannel(
            $id,
            $ownerUserId,
            $name,
            $provider,
            $encryptedConfig,
            true
        );

        return $id;
    }

    public function setActive(
        int $channelId,
        int $ownerUserId,
        bool $active
    ): bool {
        $channel = $this->findOwnedBy($channelId, $ownerUserId);

        if ($channel === null) {
            return false;
        }

        $this->channels[$channelId] = new NotificationChannel(
            $channel->id,
            $channel->ownerUserId,
            $channel->name,
            $channel->provider,
            $channel->encryptedConfig,
            $active
        );

        return true;
    }

    public function delete(
        int $channelId,
        int $ownerUserId
    ): bool {
        if ($this->findOwnedBy($channelId, $ownerUserId) === null) {
            return false;
        }

        unset($this->channels[$channelId]);

        return true;
    }
}
