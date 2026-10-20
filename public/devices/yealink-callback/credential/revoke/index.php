<?php

declare(strict_types=1);

use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\Request;
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

try {
    $runtime->deviceAdministrationService()->revokeCredential(
        $user->id,
        Request::postPositiveInt('device_id'),
        DeviceCredentialScope::YealinkCallbackWrite
    );
} catch (Throwable) {
    http_response_code(400);
    exit;
}

CsrfToken::rotate();
header('Location: /devices/', true, 303);
