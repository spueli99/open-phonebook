<?php

declare(strict_types=1);

use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\NotificationManagementPage;
use OpenPhonebook\Web\Request;
use OpenPhonebook\Web\SecurityHeaders;
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

$service = $runtime->notificationAdministrationService();
$page = new NotificationManagementPage(
    $service,
    $runtime->translator($user->locale)
);

try {
    $provider = Request::postString('provider');
    $config = match ($provider) {
        'pushover' => [
            'token' => Request::postString('token'),
            'user' => Request::postString('user'),
        ],
        'email' => [
            'recipient' => Request::postString('recipient'),
            'from' => Request::postString('from'),
        ],
        default => [],
    };

    $service->createChannel(
        $user->id,
        Request::postString('name'),
        $provider,
        $config
    );

    SecurityHeaders::sendHtml();
    echo $page->render(
        $user->id,
        CsrfToken::rotate(),
        $runtime->translator($user->locale)->get(
            'notifications.updated'
        )
    );
} catch (Throwable) {
    SecurityHeaders::sendHtml();
    echo $page->render(
        $user->id,
        CsrfToken::issue(),
        null,
        $runtime->translator($user->locale)->get(
            'error.request'
        )
    );
}
