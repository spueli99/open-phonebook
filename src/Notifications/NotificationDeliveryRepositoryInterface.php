<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use DateTimeImmutable;

interface NotificationDeliveryRepositoryInterface
{
    public function enqueue(
        int $ownerUserId,
        int $channelId,
        NotificationTrigger $trigger,
        NotificationMessage $message,
        string $encryptedRetryPayload,
        DateTimeImmutable $availableAt
    ): int;

    /**
     * @return list<NotificationDelivery>
     */
    public function claimDue(
        DateTimeImmutable $now,
        int $limit
    ): array;

    public function markSent(
        int $deliveryId,
        DateTimeImmutable $now
    ): void;

    public function reschedule(
        int $deliveryId,
        DateTimeImmutable $availableAt,
        DateTimeImmutable $errorAt
    ): void;

    public function markFailed(
        int $deliveryId,
        DateTimeImmutable $errorAt
    ): void;

    public function markCancelled(
        int $deliveryId,
        DateTimeImmutable $now
    ): void;

    /**
     * @return array{
     *     pending: int,
     *     processing: int,
     *     sent: int,
     *     failed: int,
     *     cancelled: int
     * }
     */
    public function countsOwnedBy(int $ownerUserId): array;

    /**
     * @return list<NotificationDeliveryHistoryItem>
     */
    public function recentOwnedBy(
        int $ownerUserId,
        int $limit = 50
    ): array;

    public function retryCandidateOwnedBy(
        int $deliveryId,
        int $ownerUserId
    ): ?NotificationRetryCandidate;

    public function retryFailedOwnedBy(
        int $deliveryId,
        int $ownerUserId,
        NotificationMessage $message,
        string $encryptedRetryPayload,
        DateTimeImmutable $availableAt
    ): bool;
}
