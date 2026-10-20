<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Calls;

use OpenPhonebook\Calls\CallDirection;
use OpenPhonebook\Calls\CallEventType;
use OpenPhonebook\Calls\CallSession;
use OpenPhonebook\Calls\CallSessionRepositoryInterface;
use OpenPhonebook\Calls\CallSessionState;
use RuntimeException;

final class InMemoryCallSessionRepository implements CallSessionRepositoryInterface
{
    /** @var array<int, CallSession> */
    private array $sessions = [];

    /** @var list<array{int, CallEventType, CallSessionState}> */
    public array $events = [];

    private int $nextId = 1;

    public function findOpenByDeviceAndExternalIdForUpdate(
        int $deviceId,
        string $externalCallId
    ): ?CallSession {
        foreach ($this->sessions as $session) {
            if (
                $session->deviceId === $deviceId
                && $session->externalCallId === $externalCallId
                && !$session->state->isTerminal()
            ) {
                return $session;
            }
        }

        return null;
    }

    public function create(
        int $deviceId,
        string $externalCallId,
        CallDirection $direction,
        CallSessionState $state,
        ?string $localNumber,
        ?string $remoteNumber,
        ?string $contactName
    ): CallSession {
        $session = new CallSession(
            $this->nextId++,
            $deviceId,
            $externalCallId,
            $direction,
            $state,
            $localNumber,
            $remoteNumber,
            $contactName
        );
        $this->sessions[$session->id] = $session;

        return $session;
    }

    public function updateState(
        int $sessionId,
        CallSessionState $state,
        ?string $contactName
    ): CallSession {
        $current = $this->sessions[$sessionId] ?? null;

        if ($current === null) {
            throw new RuntimeException('Call session not found.');
        }

        $updated = new CallSession(
            $current->id,
            $current->deviceId,
            $current->externalCallId,
            $current->direction,
            $state,
            $current->localNumber,
            $current->remoteNumber,
            $contactName
        );
        $this->sessions[$sessionId] = $updated;

        return $updated;
    }

    public function appendEvent(
        int $sessionId,
        CallEventType $eventType,
        CallSessionState $resultingState
    ): void {
        $this->events[] = [$sessionId, $eventType, $resultingState];
    }
}
