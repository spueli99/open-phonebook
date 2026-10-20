<?php

declare(strict_types=1);

use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\NotificationManagementPage;
use OpenPhonebook\Web\SecurityHeaders;
use OpenPhonebook\Web\WebRuntime;
use Throwable;

$projectRoot = dirname(__DIR__, 2);
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

SecurityHeaders::sendHtml();

echo (new NotificationManagementPage(
    $runtime->notificationAdministrationService(),
    $runtime->translator($user->locale)
))->render(
    $user->id,
    CsrfToken::issue()
);
