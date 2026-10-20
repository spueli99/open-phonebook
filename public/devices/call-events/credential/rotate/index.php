<?php

declare(strict_types=1);

use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\DeviceManagementPage;
use OpenPhonebook\Web\Request;
use OpenPhonebook\Web\SecurityHeaders;
use OpenPhonebook\Web\WebRuntime;
use Throwable;

$projectRoot = dirname(__DIR__, 5);
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

$service = $runtime->deviceAdministrationService();
$page = new DeviceManagementPage(
    $service,
    $runtime->translator($user->locale)
);

try {
    $credential = $service->rotateCredential(
        $user->id,
        Request::postPositiveInt('device_id'),
        DeviceCredentialScope::CallEventsWrite
    );

    SecurityHeaders::sendHtml();
    echo $page->render(
        $user->id,
        $user->timezone,
        CsrfToken::rotate(),
        $credential
    );
} catch (Throwable) {
    SecurityHeaders::sendHtml();
    echo $page->render(
        $user->id,
        $user->timezone,
        CsrfToken::issue(),
        null,
        null,
        $runtime->translator($user->locale)->get(
            'error.request'
        )
    );
}
