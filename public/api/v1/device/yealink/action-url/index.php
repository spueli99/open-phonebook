<?php

declare(strict_types=1);

use OpenPhonebook\Calls\YealinkActionUrlAdapter;
use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Web\DeviceRuntime;
use OpenPhonebook\Web\SecurityHeaders;
use OpenPhonebook\Web\Request;
use Throwable;

$projectRoot = dirname(__DIR__, 6);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    exit;
}

require $autoload;

SecurityHeaders::sendPrivate();

$clientIp = Request::clientIp();
$throttleEndpoint = 'yealink-action-url';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

if (!Request::isHttps()) {
    http_response_code(400);
    exit;
}

try {
    $runtime = DeviceRuntime::boot($projectRoot);
} catch (Throwable) {
    http_response_code(503);
    exit;
}

if ($runtime->throttle->isBlocked($throttleEndpoint, $clientIp)) {
    http_response_code(429);
    header('Retry-After: 900');
    exit;
}

$device = $runtime->authentication->authenticateBasic(
    $_SERVER,
    DeviceCredentialScope::YealinkCallbackWrite
);

if ($device === null) {
    $runtime->throttle->registerFailure(
        $throttleEndpoint,
        $clientIp
    );
    http_response_code(401);
    header(
        'WWW-Authenticate: Basic realm="Open Phonebook Yealink callback"'
    );
    exit;
}

$runtime->throttle->clear($throttleEndpoint, $clientIp);

$eventName = $_GET['event'] ?? null;

if (!is_string($eventName) || $eventName === '') {
    http_response_code(400);
    exit;
}

$parameters = [];

foreach (['call_id', 'local', 'remote'] as $key) {
    if (!array_key_exists($key, $_GET)) {
        continue;
    }

    if (!is_string($_GET[$key])) {
        http_response_code(400);
        exit;
    }

    $parameters[$key] = $_GET[$key];
}

try {
    $event = (new YealinkActionUrlAdapter())->normalize(
        $eventName,
        $parameters
    );
    $result = $runtime->callEvents->record($device, $event);
} catch (Throwable) {
    http_response_code(400);
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

echo "OK\n";
