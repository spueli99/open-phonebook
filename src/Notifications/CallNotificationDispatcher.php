<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use OpenPhonebook\Calls\CallDirection;
use OpenPhonebook\Calls\CallEventResult;
use OpenPhonebook\Calls\CallSessionState;
use OpenPhonebook\Devices\AuthenticatedDevice;
use OpenPhonebook\Clock\ClockInterface;
use Throwable;

final class CallNotificationDispatcher
{
    public function __construct(
        private readonly NotificationRuleRepositoryInterface $rules,
        private readonly NotificationChannelRepositoryInterface $channels,
        private readonly NotificationDeliveryRepositoryInterface $deliveries,
        private readonly NotificationMessageCipher $messageCipher,
        private readonly QuietHoursPolicy $quietHours,
        private readonly ClockInterface $clock,
    ) {
    }

    public function dispatch(
        AuthenticatedDevice $device,
        CallEventResult $result
    ): void {
        $trigger = $this->triggerFor($result);

        if ($trigger === null) {
            return;
        }

        $message = $this->messageFor($trigger, $result);

        foreach (
            $this->rules->matching(
                $device->ownerUserId,
                $device->deviceId,
                $trigger
            ) as $rule
        ) {
            try {
                $channel = $this->channels->findOwnedBy(
                    $rule->channelId,
                    $device->ownerUserId
                );

                if ($channel === null || !$channel->isActive) {
                    continue;
                }

                if ($this->quietHours->isQuiet($rule)) {
                    continue;
                }

                $this->deliveries->enqueue(
                    $device->ownerUserId,
                    $channel->id,
                    $trigger,
                    $message,
                    $this->messageCipher->encrypt($message),
                    $this->clock->now()
                );
            } catch (Throwable) {
                continue;
            }
        }
    }

    private function triggerFor(
        CallEventResult $result
    ): ?NotificationTrigger {
        $session = $result->session;

        if (
            $result->created
            && $session->direction === CallDirection::Incoming
            && $session->state === CallSessionState::Ringing
        ) {
            return NotificationTrigger::Incoming;
        }

        return match ($session->state) {
            CallSessionState::Missed => NotificationTrigger::Missed,
            CallSessionState::Completed => NotificationTrigger::Completed,
            default => null,
        };
    }

    private function messageFor(
        NotificationTrigger $trigger,
        CallEventResult $result
    ): NotificationMessage {
        $session = $result->session;
        $remote = $session->contactName
            ?? $session->remoteNumber
            ?? 'Unknown caller';

        return match ($trigger) {
            NotificationTrigger::Incoming => new NotificationMessage(
                'Incoming call',
                'Incoming call from ' . $remote
            ),
            NotificationTrigger::Missed => new NotificationMessage(
                'Missed call',
                'Missed call from ' . $remote
            ),
            NotificationTrigger::Completed => new NotificationMessage(
                'Call completed',
                'Call with ' . $remote . ' completed'
            ),
        };
    }
}
