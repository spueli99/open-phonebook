<?php

declare(strict_types=1);

use OpenPhonebook\Calls\CallDirection;
use OpenPhonebook\Calls\CallSessionRepository;
use OpenPhonebook\Calls\CallSessionState;
use OpenPhonebook\Database\ConnectionFactory;
use RuntimeException;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$database = ConnectionFactory::create([
    'host' => getenv('OPENPHONEBOOK_DB_HOST') ?: '',
    'port' => (int) (getenv('OPENPHONEBOOK_DB_PORT') ?: 3306),
    'name' => getenv('OPENPHONEBOOK_DB_NAME') ?: '',
    'user' => getenv('OPENPHONEBOOK_DB_USER') ?: '',
    'password' => getenv('OPENPHONEBOOK_DB_PASSWORD') ?: '',
]);

$database->exec(
    "INSERT INTO users (
        email,
        display_name,
        password_hash,
        `role`,
        locale,
        timezone,
        is_active,
        created_at,
        updated_at
    ) VALUES (
        'call-smoke@example.invalid',
        'Call Smoke',
        'not-used',
        'member',
        'en',
        'UTC',
        1,
        UTC_TIMESTAMP(),
        UTC_TIMESTAMP()
    )"
);

$userId = (int) $database->lastInsertId();

$statement = $database->prepare(
    'INSERT INTO devices (
        owner_user_id,
        name,
        is_active,
        created_at,
        updated_at
     ) VALUES (
        :owner_user_id,
        :name,
        1,
        UTC_TIMESTAMP(),
        UTC_TIMESTAMP()
     )'
);
$statement->execute([
    'owner_user_id' => $userId,
    'name' => 'MariaDB call smoke',
]);

$deviceId = (int) $database->lastInsertId();
$repository = new CallSessionRepository($database);

$session = $repository->create(
    $deviceId,
    'mariadb-smoke-1',
    CallDirection::Incoming,
    CallSessionState::Ringing,
    '100',
    '+492150123456',
    'Max Mustermann'
);

$connected = $repository->updateState(
    $session->id,
    CallSessionState::Connected,
    $session->contactName
);

if ($connected->state !== CallSessionState::Connected) {
    throw new RuntimeException('Connected call state was not persisted.');
}

$completed = $repository->updateState(
    $session->id,
    CallSessionState::Completed,
    $session->contactName
);

if ($completed->state !== CallSessionState::Completed) {
    throw new RuntimeException('Completed call state was not persisted.');
}

$check = $database->prepare(
    'SELECT active_external_call_id, ended_at
     FROM call_sessions
     WHERE id = :id'
);
$check->execute(['id' => $session->id]);
$row = $check->fetch();

if (
    !is_array($row)
    || $row['active_external_call_id'] !== null
    || $row['ended_at'] === null
) {
    throw new RuntimeException(
        'Terminal call state did not release the active call identifier.'
    );
}

echo "MariaDB call-session smoke passed.\n";
