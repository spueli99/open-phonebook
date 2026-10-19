<?php

declare(strict_types=1);

use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\DeviceManagementPage;
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

SecurityHeaders::sendHtml();

echo (new DeviceManagementPage(
    $runtime->deviceAdministrationService(),
    $runtime->translator($user->locale)
))->render(
    $user->id,
    CsrfToken::issue()
);
