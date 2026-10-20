<?php

declare(strict_types=1);

use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\Request;
use OpenPhonebook\Web\WebRuntime;
use Throwable;

$projectRoot = dirname(__DIR__, 4);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    exit;
}

require $autoload;

try {
    $runtime = WebRuntime::boot($projectRoot);
} catch (Throwable) {
    http_response_code(503);
    exit;
}

$user = $runtime->userSession->user();

if ($user === null) {
    http_response_code(401);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!CsrfToken::verify(Request::postString('_csrf'))) {
    http_response_code(400);
    exit;
}

try {
    $runtime->notificationAdministrationService()->registerWebPush(
        $user->id,
        Request::postString('endpoint'),
        Request::postString('p256dh'),
        Request::postString('auth'),
        Request::postString('content_encoding'),
        Request::postString('browser_id')
    );
} catch (Throwable) {
    http_response_code(400);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

echo '{"ok":true}';
