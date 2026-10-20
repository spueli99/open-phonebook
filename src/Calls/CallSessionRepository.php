<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

use PDO;
use RuntimeException;

final class CallSessionRepository implements CallSessionRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function findByDeviceAndExternalIdForUpdate(
        int $deviceId,
        string $externalCallId
    ): ?CallSession {
        $statement = $this->database->prepare(
            'SELECT id, device_id, external_call_id, direction, state,
                    local_number, remote_number, contact_name
             FROM call_sessions
             WHERE device_id = :device_id
               AND external_call_id = :external_call_id
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute([
            'device_id' => $deviceId,
            'external_call_id' => $externalCallId,
        ]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException(
                'Unexpected call session query result.'
            );
        }

        return $this->hydrate($row);
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
        $statement = $this->database->prepare(
            'INSERT INTO call_sessions (
                device_id,
                external_call_id,
                direction,
                state,
                local_number,
                remote_number,
                contact_name,
                started_at,
                updated_at
             ) VALUES (
                :device_id,
                :external_call_id,
                :direction,
                :state,
                :local_number,
                :remote_number,
                :contact_name,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )'
        );
        $statement->execute([
            'device_id' => $deviceId,
            'external_call_id' => $externalCallId,
            'direction' => $direction->value,
            'state' => $state->value,
            'local_number' => $localNumber,
            'remote_number' => $remoteNumber,
            'contact_name' => $contactName,
        ]);

        return new CallSession(
            (int) $this->database->lastInsertId(),
            $deviceId,
            $externalCallId,
            $direction,
            $state,
            $localNumber,
            $remoteNumber,
            $contactName
        );
    }

    public function updateState(
        int $sessionId,
        CallSessionState $state,
        ?string $contactName
    ): CallSession {
        $statement = $this->database->prepare(
            'UPDATE call_sessions
             SET state = :state,
                 contact_name = :contact_name,
                 updated_at = UTC_TIMESTAMP(),
                 ended_at = CASE
                     WHEN :terminal = 1 THEN UTC_TIMESTAMP()
                     ELSE ended_at
                 END
             WHERE id = :id'
        );
        $statement->execute([
            'state' => $state->value,
            'contact_name' => $contactName,
            'terminal' => $state->isTerminal() ? 1 : 0,
            'id' => $sessionId,
        ]);

        $load = $this->database->prepare(
            'SELECT id, device_id, external_call_id, direction, state,
                    local_number, remote_number, contact_name
             FROM call_sessions
             WHERE id = :id
             LIMIT 1'
        );
        $load->execute(['id' => $sessionId]);
        $row = $load->fetch();

        if ($row === false) {
            throw new RuntimeException('Call session could not be loaded.');
        }

        if (!is_array($row)) {
            throw new RuntimeException(
                'Unexpected call session query result.'
            );
        }

        return $this->hydrate($row);
    }

    public function appendEvent(
        int $sessionId,
        CallEventType $eventType,
        CallSessionState $resultingState
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO call_events (
                call_session_id,
                event_type,
                resulting_state,
                occurred_at
             ) VALUES (
                :call_session_id,
                :event_type,
                :resulting_state,
                UTC_TIMESTAMP()
             )'
        );
        $statement->execute([
            'call_session_id' => $sessionId,
            'event_type' => $eventType->value,
            'resulting_state' => $resultingState->value,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): CallSession
    {
        return new CallSession(
            (int) $row['id'],
            (int) $row['device_id'],
            (string) $row['external_call_id'],
            CallDirection::from((string) $row['direction']),
            CallSessionState::from((string) $row['state']),
            $row['local_number'] === null
                ? null
                : (string) $row['local_number'],
            $row['remote_number'] === null
                ? null
                : (string) $row['remote_number'],
            $row['contact_name'] === null
                ? null
                : (string) $row['contact_name'],
        );
    }
}
