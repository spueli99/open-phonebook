<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Notifications;

use DateTimeImmutable;
use OpenPhonebook\Notifications\NotificationDelivery;
use OpenPhonebook\Notifications\NotificationDeliveryRepositoryInterface;
use OpenPhonebook\Notifications\NotificationDeliveryStatus;
use OpenPhonebook\Notifications\NotificationMessage;
use OpenPhonebook\Notifications\NotificationTrigger;

final class InMemoryNotificationDeliveryRepository implements NotificationDeliveryRepositoryInterface
{
    /** @var array<int, NotificationDelivery> */
    private array $deliveries = [];

    private int $nextId = 1;

    public function enqueue(
        int $ownerUserId,
        int $channelId,
        NotificationTrigger $trigger,
        NotificationMessage $message,
        DateTimeImmutable $availableAt
    ): int {
        $id = $this->nextId++;
        $this->deliveries[$id] = new NotificationDelivery(
            $id,
            $ownerUserId,
            $channelId,
            $trigger,
            $message->title,
            $message->body,
            NotificationDeliveryStatus::Pending,
            0,
            $availableAt
        );

        return $id;
    }

    public function claimDue(
        DateTimeImmutable $now,
        int $limit
    ): array {
        $claimed = [];

        foreach ($this->deliveries as $id => $delivery) {
            if (
                count($claimed) >= $limit
                || $delivery->status !== NotificationDeliveryStatus::Pending
                || $delivery->availableAt > $now
            ) {
                continue;
            }

            $updated = new NotificationDelivery(
                $delivery->id,
                $delivery->ownerUserId,
                $delivery->channelId,
                $delivery->trigger,
                $delivery->title,
                $delivery->body,
                NotificationDeliveryStatus::Processing,
                $delivery->attempts + 1,
                $delivery->availableAt
            );
            $this->deliveries[$id] = $updated;
            $claimed[] = $updated;
        }

        return $claimed;
    }

    public function markSent(
        int $deliveryId,
        DateTimeImmutable $now
    ): void {
        $this->withStatus(
            $deliveryId,
            NotificationDeliveryStatus::Sent
        );
    }

    public function reschedule(
        int $deliveryId,
        DateTimeImmutable $availableAt,
        DateTimeImmutable $errorAt
    ): void {
        $delivery = $this->deliveries[$deliveryId];
        $this->deliveries[$deliveryId] = new NotificationDelivery(
            $delivery->id,
            $delivery->ownerUserId,
            $delivery->channelId,
            $delivery->trigger,
            $delivery->title,
            $delivery->body,
            NotificationDeliveryStatus::Pending,
            $delivery->attempts,
            $availableAt
        );
    }

    public function markFailed(
        int $deliveryId,
        DateTimeImmutable $errorAt
    ): void {
        $this->withStatus(
            $deliveryId,
            NotificationDeliveryStatus::Failed
        );
    }

    public function markCancelled(
        int $deliveryId,
        DateTimeImmutable $now
    ): void {
        $this->withStatus(
            $deliveryId,
            NotificationDeliveryStatus::Cancelled
        );
    }

    public function countsOwnedBy(int $ownerUserId): array
    {
        $counts = [
            'pending' => 0,
            'processing' => 0,
            'sent' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ];

        foreach ($this->deliveries as $delivery) {
            if ($delivery->ownerUserId !== $ownerUserId) {
                continue;
            }

            $counts[$delivery->status->value]++;
        }

        return $counts;
    }

    public function get(int $id): NotificationDelivery
    {
        return $this->deliveries[$id];
    }

    private function withStatus(
        int $id,
        NotificationDeliveryStatus $status
    ): void {
        $delivery = $this->deliveries[$id];
        $this->deliveries[$id] = new NotificationDelivery(
            $delivery->id,
            $delivery->ownerUserId,
            $delivery->channelId,
            $delivery->trigger,
            $delivery->title,
            $delivery->body,
            $status,
            $delivery->attempts,
            $delivery->availableAt
        );
    }
}
