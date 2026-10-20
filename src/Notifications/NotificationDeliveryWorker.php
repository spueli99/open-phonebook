<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use DateInterval;
use OpenPhonebook\Clock\ClockInterface;
use Throwable;

final class NotificationDeliveryWorker
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly NotificationDeliveryRepositoryInterface $deliveries,
        private readonly NotificationChannelRepositoryInterface $channels,
        private readonly NotificationProviderRegistry $providers,
        private readonly NotificationConfigCipher $configCipher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function run(int $limit = 50): NotificationDeliveryResult
    {
        $jobs = $this->deliveries->claimDue(
            $this->clock->now(),
            $limit
        );
        $sent = 0;
        $rescheduled = 0;
        $failed = 0;
        $cancelled = 0;

        foreach ($jobs as $delivery) {
            $now = $this->clock->now();

            if ($delivery->attempts > self::MAX_ATTEMPTS) {
                $this->deliveries->markFailed($delivery->id, $now);
                $failed++;
                continue;
            }

            if ($delivery->channelId === null) {
                $this->deliveries->markCancelled($delivery->id, $now);
                $cancelled++;
                continue;
            }

            $channel = $this->channels->findOwnedBy(
                $delivery->channelId,
                $delivery->ownerUserId
            );

            if ($channel === null || !$channel->isActive) {
                $this->deliveries->markCancelled($delivery->id, $now);
                $cancelled++;
                continue;
            }

            $provider = $this->providers->get($channel->provider);

            if ($provider === null) {
                $this->deliveries->markFailed($delivery->id, $now);
                $failed++;
                continue;
            }

            try {
                $config = $this->configCipher->decrypt(
                    $channel->encryptedConfig
                );
            } catch (Throwable) {
                $this->deliveries->markFailed($delivery->id, $now);
                $failed++;
                continue;
            }

            try {
                $provider->send(
                    $config,
                    new NotificationMessage(
                        $delivery->title,
                        $delivery->body
                    )
                );
                $this->deliveries->markSent($delivery->id, $now);
                $sent++;
            } catch (ExpiredNotificationChannelException) {
                $this->channels->setActive(
                    $channel->id,
                    $delivery->ownerUserId,
                    false
                );
                $this->deliveries->markCancelled(
                    $delivery->id,
                    $now
                );
                $cancelled++;
            } catch (Throwable) {
                if ($delivery->attempts >= self::MAX_ATTEMPTS) {
                    $this->deliveries->markFailed(
                        $delivery->id,
                        $now
                    );
                    $failed++;
                    continue;
                }

                $this->deliveries->reschedule(
                    $delivery->id,
                    $now->add(
                        new DateInterval(
                            'PT'
                            . $this->backoffSeconds(
                                $delivery->attempts
                            )
                            . 'S'
                        )
                    ),
                    $now
                );
                $rescheduled++;
            }
        }

        return new NotificationDeliveryResult(
            count($jobs),
            $sent,
            $rescheduled,
            $failed,
            $cancelled
        );
    }

    private function backoffSeconds(int $attempt): int
    {
        return match ($attempt) {
            1 => 60,
            2 => 300,
            3 => 900,
            default => 3600,
        };
    }
}
