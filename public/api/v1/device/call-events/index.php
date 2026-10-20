<?php

declare(strict_types=1);

use OpenPhonebook\Calls\JsonCallEventParser;
use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Web\DeviceRuntime;
use Throwable;

$projectRoot = dirname(__DIR__, 5);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    exit;
}

require $autoload;

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
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
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
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit;
}

$body = file_get_contents('php://input', false, null, 0, 16_385);

if (!is_string($body) || strlen($body) > 16_384) {
    http_response_code(413);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit;
}

try {
    $event = (new JsonCallEventParser())->parse($body);
    $result = $runtime->callEvents->record($device, $event);
} catch (Throwable) {
    http_response_code(400);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

echo json_encode(
    [
        'call_id' => $result->session->externalCallId,
        'state' => $result->session->state->value,
        'direction' => $result->session->direction->value,
        'contact_name' => $result->session->contactName,
    ],
    JSON_THROW_ON_ERROR
) . "\n";
