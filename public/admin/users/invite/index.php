<?php

declare(strict_types=1);

use OpenPhonebook\Auth\Authorization;
use OpenPhonebook\Auth\Permission;
use OpenPhonebook\Auth\Role;
use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\UserManagement\InvitationAdministrationService;
use OpenPhonebook\UserManagement\InvitationRepository;
use OpenPhonebook\UserManagement\InvitationService;
use OpenPhonebook\UserManagement\UserAdministrationRepository;
use OpenPhonebook\Web\InvitationCreateView;
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

$translator = $runtime->translator($user->locale);

if (!(new Authorization())->allows($user, Permission::InviteUsers)) {
    http_response_code(403);
    SecurityHeaders::sendHtml();
    echo '<p>' . htmlspecialchars(
        $translator->get('error.forbidden'),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . '</p>';
    exit;
}

$invitationUrl = null;
$mailSent = false;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CsrfToken::verify(Request::postString('_csrf'))) {
        http_response_code(400);
        $error = $translator->get('error.request');
    } else {
        try {
            $role = Role::from(Request::postString('role'));
            $service = new InvitationService(
                new InvitationRepository($runtime->database),
                new UserAdministrationRepository($runtime->database),
                new SystemClock(),
                $runtime->applicationKey,
            );
            $result = $service->create(
                Request::postString('email'),
                $role,
                $user->id
            );

            $mailer = $runtime->invitationMailer();

            if ($mailer !== null) {
                try {
                    $mailer->send($result);
                    $mailSent = true;
                } catch (Throwable) {
                    (new InvitationAdministrationService(
                        new InvitationRepository($runtime->database),
                        new SystemClock(),
                        $runtime->applicationKey,
                    ))->revoke($result->id);

                    throw new RuntimeException(
                        'Invitation email could not be sent.'
                    );
                }
            } else {
                $baseUrl = $runtime->config->get('app.base_url', '');
                if (!is_string($baseUrl) || $baseUrl === '') {
                    throw new RuntimeException('Application base URL is missing.');
                }

                $invitationUrl = rtrim($baseUrl, '/')
                    . '/invite/accept/#'
                    . rawurlencode($result->token);
            }

            CsrfToken::rotate();
        } catch (Throwable) {
            $error = $translator->get('error.request');
        }
    }
}

SecurityHeaders::sendHtml();

echo InvitationCreateView::render(
    $translator,
    CsrfToken::issue(),
    $invitationUrl,
    $error,
    $mailSent
);
