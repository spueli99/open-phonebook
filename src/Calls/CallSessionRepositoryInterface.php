<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

interface CallSessionRepositoryInterface
{
    public function findOpenByDeviceAndExternalIdForUpdate(
        int $deviceId,
        string $externalCallId
    ): ?CallSession;

    public function create(
        int $deviceId,
        string $externalCallId,
        CallDirection $direction,
        CallSessionState $state,
        ?string $localNumber,
        ?string $remoteNumber,
        ?string $contactName
    ): CallSession;

    public function updateState(
        int $sessionId,
        CallSessionState $state,
        ?string $contactName
    ): CallSession;

    public function appendEvent(
        int $sessionId,
        CallEventType $eventType,
        CallSessionState $resultingState
    ): void;
}
