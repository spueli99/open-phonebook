<?php

declare(strict_types=1);

use OpenPhonebook\Notifications\NotificationTrigger;
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
    header('Location: /login/', true, 302);
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

$deviceValue = Request::postString('device_id');
$deviceId = $deviceValue === ''
    ? null
    : Request::postPositiveInt('device_id');
$trigger = NotificationTrigger::tryFrom(
    Request::postString('trigger')
);

if ($trigger === null || ($deviceValue !== '' && $deviceId === 0)) {
    http_response_code(400);
    exit;
}

try {
    $runtime->notificationAdministrationService()->createRule(
        $user->id,
        Request::postPositiveInt('channel_id'),
        $deviceId,
        $trigger,
        ($quietStart = Request::postString('quiet_start')) === ''
            ? null
            : $quietStart,
        ($quietEnd = Request::postString('quiet_end')) === ''
            ? null
            : $quietEnd
    );
} catch (Throwable) {
    http_response_code(400);
    exit;
}

CsrfToken::rotate();
header('Location: /notifications/', true, 303);
