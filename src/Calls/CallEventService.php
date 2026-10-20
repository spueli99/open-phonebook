<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

use OpenPhonebook\Database\TransactionManagerInterface;
use OpenPhonebook\Devices\AuthenticatedDevice;
use OpenPhonebook\Devices\DeviceCredentialScope;
use RuntimeException;

final class CallEventService
{
    public function __construct(
        private readonly CallSessionRepositoryInterface $sessions,
        private readonly CallerLookupService $callerLookup,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function record(
        AuthenticatedDevice $device,
        NormalizedCallEvent $event
    ): CallEventResult {
        if ($device->scope !== DeviceCredentialScope::CallEventsWrite) {
            throw new RuntimeException(
                'Device credential scope cannot write call events.'
            );
        }

        $this->validate($event);

        return $this->transactions->transactional(
            function () use ($device, $event): CallEventResult {
                $session = $this->sessions
                    ->findByDeviceAndExternalIdForUpdate(
                        $device->deviceId,
                        $event->externalCallId
                    );

                if ($session === null) {
                    return $this->createSession($device, $event);
                }

                if (
                    $event->direction !== null
                    && $session->direction !== $event->direction
                ) {
                    throw new RuntimeException(
                        'Call direction cannot change.'
                    );
                }

                $state = $this->nextState($session, $event->type);
                $contactName = $session->contactName
                    ?? $this->callerLookup->findDisplayName(
                        $device,
                        $event->remoteNumber ?? $session->remoteNumber
                    );

                $session = $this->sessions->updateState(
                    $session->id,
                    $state,
                    $contactName
                );
                $this->sessions->appendEvent(
                    $session->id,
                    $event->type,
                    $state
                );

                return new CallEventResult($session, false);
            }
        );
    }

    private function createSession(
        AuthenticatedDevice $device,
        NormalizedCallEvent $event
    ): CallEventResult {
        if ($event->direction === null) {
            throw new RuntimeException(
                'Initial call event requires direction.'
            );
        }

        $state = match ($event->type) {
            CallEventType::Ringing => $event->direction
                === CallDirection::Incoming
                    ? CallSessionState::Ringing
                    : throw new RuntimeException(
                        'Ringing must be incoming.'
                    ),
            CallEventType::Calling => $event->direction
                === CallDirection::Outgoing
                    ? CallSessionState::Calling
                    : throw new RuntimeException(
                        'Calling must be outgoing.'
                    ),
            default => throw new RuntimeException(
                'Call session has no initial event.'
            ),
        };

        $contactName = $this->callerLookup->findDisplayName(
            $device,
            $event->remoteNumber
        );
        $session = $this->sessions->create(
            $device->deviceId,
            $event->externalCallId,
            $event->direction,
            $state,
            $event->localNumber,
            $event->remoteNumber,
            $contactName
        );
        $this->sessions->appendEvent(
            $session->id,
            $event->type,
            $state
        );

        return new CallEventResult($session, true);
    }

    private function nextState(
        CallSession $session,
        CallEventType $event
    ): CallSessionState {
        if ($session->state->isTerminal()) {
            throw new RuntimeException('Call session is already terminal.');
        }

        return match ($event) {
            CallEventType::Ringing => (
                $session->direction === CallDirection::Incoming
                && $session->state === CallSessionState::Ringing
            )
                ? CallSessionState::Ringing
                : throw new RuntimeException('Invalid ringing transition.'),
            CallEventType::Calling => (
                $session->direction === CallDirection::Outgoing
                && $session->state === CallSessionState::Calling
            )
                ? CallSessionState::Calling
                : throw new RuntimeException('Invalid calling transition.'),
            CallEventType::Connected => in_array(
                $session->state,
                [
                    CallSessionState::Ringing,
                    CallSessionState::Calling,
                    CallSessionState::Connected,
                ],
                true
            )
                ? CallSessionState::Connected
                : throw new RuntimeException('Invalid connected transition.'),
            CallEventType::Idle => match ($session->state) {
                CallSessionState::Connected => CallSessionState::Completed,
                CallSessionState::Ringing => CallSessionState::Missed,
                CallSessionState::Calling => CallSessionState::Cancelled,
                default => throw new RuntimeException(
                    'Invalid idle transition.'
                ),
            },
        };
    }

    private function validate(NormalizedCallEvent $event): void
    {
        if (
            $event->externalCallId === ''
            || mb_strlen($event->externalCallId) > 191
        ) {
            throw new RuntimeException('Invalid call identifier.');
        }

        foreach (
            [$event->localNumber, $event->remoteNumber] as $number
        ) {
            if ($number !== null && mb_strlen($number) > 120) {
                throw new RuntimeException('Call number is too long.');
            }
        }
    }
}
