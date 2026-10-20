<?php

declare(strict_types=1);

use OpenPhonebook\Calls\JsonCallEventParser;
use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Web\DeviceRuntime;
use OpenPhonebook\Web\SecurityHeaders;
use Throwable;

$projectRoot = dirname(__DIR__, 5);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    exit;
}

require $autoload;

SecurityHeaders::sendPrivate();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

try {
    $runtime = DeviceRuntime::boot($projectRoot);
} catch (Throwable) {
    http_response_code(503);
    exit;
}

$device = $runtime->authentication->authenticate(
    $_SERVER,
    DeviceCredentialScope::CallEventsWrite
);

if ($device === null) {
    http_response_code(401);
    header(
        'WWW-Authenticate: Bearer realm="Open Phonebook"',
        false
    );
    header(
        'WWW-Authenticate: Basic realm="Open Phonebook"',
        false
    );
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (
    !is_string($contentType)
    || !str_starts_with(
        strtolower($contentType),
        'application/json'
    )
) {
    http_response_code(415);
    exit;
}

$body = file_get_contents('php://input', false, null, 0, 16_385);

if (!is_string($body) || strlen($body) > 16_384) {
    http_response_code(413);
    exit;
}

try {
    $event = (new JsonCallEventParser())->parse($body);
    $result = $runtime->callEvents->record($device, $event);
} catch (Throwable) {
    http_response_code(400);
    exit;
}

try {
    $runtime->notifications->dispatch($device, $result);
} catch (Throwable) {
    // Notification failures must not change the accepted call event.
}

try {
    $runtime->authentication->markUsed($device);
} catch (Throwable) {
    // Usage telemetry must not break an accepted call event.
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode(
    [
        'call_id' => $result->session->externalCallId,
        'state' => $result->session->state->value,
        'direction' => $result->session->direction->value,
        'contact_name' => $result->session->contactName,
    ],
    JSON_THROW_ON_ERROR
) . "\n";
