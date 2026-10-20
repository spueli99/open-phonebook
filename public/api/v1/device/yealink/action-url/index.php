<?php

declare(strict_types=1);

use OpenPhonebook\Calls\YealinkActionUrlAdapter;
use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Web\DeviceRuntime;
use OpenPhonebook\Web\Request;
use Throwable;

$projectRoot = dirname(__DIR__, 6);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    exit;
}

require $autoload;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

if (!Request::isHttps()) {
    http_response_code(400);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit;
}

try {
    $runtime = DeviceRuntime::boot($projectRoot);
} catch (Throwable) {
    http_response_code(503);
    exit;
}

$device = $runtime->authentication->authenticateBasic(
    $_SERVER,
    DeviceCredentialScope::YealinkCallbackWrite
);

if ($device === null) {
    http_response_code(401);
    header(
        'WWW-Authenticate: Basic realm="Open Phonebook Yealink callback"'
    );
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    exit;
}

$eventName = $_GET['event'] ?? null;

if (!is_string($eventName) || $eventName === '') {
    http_response_code(400);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit;
}

try {
    $event = (new YealinkActionUrlAdapter())->normalize(
        $eventName,
        $_GET
    );
    $result = $runtime->callEvents->record($device, $event);
} catch (Throwable) {
    http_response_code(400);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit;
}

try {
    $runtime->notifications->dispatch($device, $result);
} catch (Throwable) {
    // Notification failures must not change the accepted callback.
}

try {
    $runtime->authentication->markUsed($device);
} catch (Throwable) {
    // Usage telemetry must not break an accepted callback.
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

echo "OK\n";
