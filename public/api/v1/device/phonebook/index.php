<?php

declare(strict_types=1);

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

$device = $runtime->authentication->authenticate(
    $_SERVER,
    DeviceCredentialScope::PhonebookRead
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

$format = $_GET['format'] ?? '';

if (!is_string($format) || !preg_match('/^[a-z0-9_-]+$/', $format)) {
    http_response_code(400);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit;
}

$exporter = $runtime->exporters->get($format);

if ($exporter === null) {
    http_response_code(404);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit;
}

try {
    $export = $exporter->export(
        $runtime->phonebooks->entriesFor($device)
    );
} catch (Throwable) {
    http_response_code(500);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    exit;
}

header('Content-Type: ' . $export->contentType);
header('Content-Disposition: inline; filename="' . $export->fileName . '"');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

echo $export->body;
