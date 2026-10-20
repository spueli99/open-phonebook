<?php

declare(strict_types=1);

use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Web\DeviceRuntime;
use OpenPhonebook\Web\Request;
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

$clientIp = Request::clientIp();
$throttleEndpoint = 'phonebook';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
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

$device = $runtime->authentication->authenticate(
    $_SERVER,
    DeviceCredentialScope::PhonebookRead
);

if ($device === null) {
    $runtime->throttle->registerFailure(
        $throttleEndpoint,
        $clientIp
    );
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

$runtime->throttle->clear($throttleEndpoint, $clientIp);

$format = $_GET['format'] ?? '';

if (!is_string($format) || !preg_match('/^[a-z0-9_-]+$/', $format)) {
    http_response_code(400);
    exit;
}

$exporter = $runtime->exporters->get($format);

if ($exporter === null) {
    http_response_code(404);
    exit;
}

try {
    $export = $exporter->export(
        $runtime->phonebooks->entriesFor($device)
    );
} catch (Throwable) {
    http_response_code(500);
    exit;
}

try {
    $runtime->authentication->markUsed($device);
} catch (Throwable) {
    // Usage telemetry must not break a successful phonebook response.
}

header('Content-Type: ' . $export->contentType);
header('Content-Disposition: inline; filename="' . $export->fileName . '"');

echo $export->body;
