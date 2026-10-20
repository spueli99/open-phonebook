<?php

declare(strict_types=1);

use OpenPhonebook\Auth\Authorization;
use OpenPhonebook\Auth\Permission;
use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\UserManagement\InvitationAdministrationService;
use OpenPhonebook\UserManagement\InvitationRepository;
use OpenPhonebook\Web\InvitationCreateView;
use OpenPhonebook\Web\Request;
use OpenPhonebook\Web\SecurityHeaders;
use OpenPhonebook\Web\WebRuntime;
use RuntimeException;
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

$translator = $runtime->translator($user->locale);

if (!(new Authorization())->allows($user, Permission::InviteUsers)) {
    http_response_code(403);
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
    $service = new InvitationAdministrationService(
        new InvitationRepository($runtime->database),
        new SystemClock(),
        $runtime->applicationKey,
    );
    $result = $service->renew(
        (int) Request::postString('invitation_id'),
        $user->id
    );

    $mailSent = false;
    $mailer = $runtime->invitationMailer();

    if ($mailer === null) {
        $service->revoke($result->id);

        throw new RuntimeException(
            'Invitation mail configuration is invalid.'
        );
    }

    try {
        $mailer->send($result);
        $mailSent = true;
    } catch (Throwable) {
        $service->revoke($result->id);

        throw new RuntimeException(
            'Invitation email could not be sent.'
        );
    }
} catch (Throwable) {
    http_response_code(400);
    exit;
}

CsrfToken::rotate();
SecurityHeaders::sendHtml();

echo InvitationCreateView::render(
    $translator,
    CsrfToken::issue(),
    null,
    null,
    $mailSent
);
