<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Notifications;

use DateTimeImmutable;
use OpenPhonebook\Notifications\NotificationDelivery;
use OpenPhonebook\Notifications\NotificationDeliveryHistoryFilter;
use OpenPhonebook\Notifications\NotificationDeliveryHistoryItem;
use OpenPhonebook\Notifications\NotificationDeliveryHistoryPage;
use OpenPhonebook\Notifications\NotificationDeliveryRepositoryInterface;
use OpenPhonebook\Notifications\NotificationDeliveryStatus;
use OpenPhonebook\Notifications\NotificationMessage;
use OpenPhonebook\Notifications\NotificationRetryCandidate;
use OpenPhonebook\Notifications\NotificationTrigger;

final class InMemoryNotificationDeliveryRepository implements NotificationDeliveryRepositoryInterface
{
    /** @var array<int, NotificationDelivery> */
    private array $deliveries = [];

    /** @var array<int, string> */
    private array $retryPayloads = [];

    /** @var array<int, DateTimeImmutable> */
    private array $createdAt = [];

    /** @var array<int, DateTimeImmutable> */
    private array $lastErrorAt = [];

    /** @var array<int, DateTimeImmutable> */
    private array $sentAt = [];

    private int $nextId = 1;

    public function enqueue(
        int $ownerUserId,
        int $channelId,
        NotificationTrigger $trigger,
        NotificationMessage $message,
        string $encryptedRetryPayload,
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
        $this->retryPayloads[$id] = $encryptedRetryPayload;
        $this->createdAt[$id] = $availableAt;

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
        $this->sentAt[$deliveryId] = $now;
        $this->withStatus(
            $deliveryId,
            NotificationDeliveryStatus::Sent,
            true
        );
    }

    public function reschedule(
        int $deliveryId,
        DateTimeImmutable $availableAt,
        DateTimeImmutable $errorAt
    ): void {
        $delivery = $this->deliveries[$deliveryId];
        $this->lastErrorAt[$deliveryId] = $errorAt;
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
        $this->lastErrorAt[$deliveryId] = $errorAt;
        $this->withStatus(
            $deliveryId,
            NotificationDeliveryStatus::Failed,
            false
        );
    }

    public function markCancelled(
        int $deliveryId,
        DateTimeImmutable $now
    ): void {
        $this->withStatus(
            $deliveryId,
            NotificationDeliveryStatus::Cancelled,
            true
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

    public function historyOwnedBy(
        int $ownerUserId,
        NotificationDeliveryHistoryFilter $filter,
        int $limit = 50
    ): NotificationDeliveryHistoryPage {
        $limit = max(1, min(100, $limit));
        $ids = array_keys($this->deliveries);
        rsort($ids);
        $history = [];

        foreach ($ids as $id) {
            $delivery = $this->deliveries[$id];

            if (
                $delivery->ownerUserId !== $ownerUserId
                || (
                    $filter->status !== null
                    && $delivery->status !== $filter->status
                )
                || (
                    $filter->trigger !== null
                    && $delivery->trigger !== $filter->trigger
                )
                || (
                    $filter->channelId !== null
                    && $delivery->channelId !== $filter->channelId
                )
                || (
                    $filter->beforeId !== null
                    && $delivery->id >= $filter->beforeId
                )
            ) {
                continue;
            }

            $history[] = new NotificationDeliveryHistoryItem(
                $delivery->id,
                $delivery->channelId,
                $delivery->channelId === null
                    ? null
                    : 'Channel ' . $delivery->channelId,
                $delivery->trigger,
                $delivery->status,
                $delivery->attempts,
                $delivery->availableAt,
                $this->lastErrorAt[$id] ?? null,
                $this->sentAt[$id] ?? null,
                $this->createdAt[$id],
                $delivery->status === NotificationDeliveryStatus::Failed
                    && isset($this->retryPayloads[$id])
                    && $delivery->channelId !== null
            );

            if (count($history) > $limit) {
                break;
            }
        }

        $hasMore = count($history) > $limit;

        if ($hasMore) {
            array_pop($history);
        }

        return new NotificationDeliveryHistoryPage(
            $history,
            $hasMore ? $history[array_key_last($history)]->id : null
        );
    }

    public function retryCandidateOwnedBy(
        int $deliveryId,
        int $ownerUserId
    ): ?NotificationRetryCandidate {
        $delivery = $this->deliveries[$deliveryId] ?? null;

        if (
            $delivery === null
            || $delivery->ownerUserId !== $ownerUserId
            || $delivery->status !== NotificationDeliveryStatus::Failed
            || !isset($this->retryPayloads[$deliveryId])
        ) {
            return null;
        }

        return new NotificationRetryCandidate(
            $delivery->id,
            $delivery->ownerUserId,
            $delivery->channelId,
            $delivery->trigger,
            $this->retryPayloads[$deliveryId]
        );
    }

    public function retryFailedOwnedBy(
        int $deliveryId,
        int $ownerUserId,
        NotificationMessage $message,
        string $encryptedRetryPayload,
        DateTimeImmutable $availableAt
    ): bool {
        $delivery = $this->deliveries[$deliveryId] ?? null;

        if (
            $delivery === null
            || $delivery->ownerUserId !== $ownerUserId
            || $delivery->status !== NotificationDeliveryStatus::Failed
            || $delivery->channelId === null
            || !isset($this->retryPayloads[$deliveryId])
        ) {
            return false;
        }

        $this->deliveries[$deliveryId] = new NotificationDelivery(
            $delivery->id,
            $delivery->ownerUserId,
            $delivery->channelId,
            $delivery->trigger,
            $message->title,
            $message->body,
            NotificationDeliveryStatus::Pending,
            0,
            $availableAt
        );
        $this->retryPayloads[$deliveryId] = $encryptedRetryPayload;
        unset(
            $this->lastErrorAt[$deliveryId],
            $this->sentAt[$deliveryId]
        );

        return true;
    }

    public function get(int $id): NotificationDelivery
    {
        return $this->deliveries[$id];
    }

    private function withStatus(
        int $id,
        NotificationDeliveryStatus $status,
        bool $clearRetryPayload
    ): void {
        $delivery = $this->deliveries[$id];
        $this->deliveries[$id] = new NotificationDelivery(
            $delivery->id,
            $delivery->ownerUserId,
            $delivery->channelId,
            $delivery->trigger,
            '',
            '',
            $status,
            $delivery->attempts,
            $delivery->availableAt
        );

        if ($clearRetryPayload) {
            unset($this->retryPayloads[$id]);
        }
    }
}
