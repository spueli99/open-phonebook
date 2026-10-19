<?php

declare(strict_types=1);

use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\UserManagement\InvitationAcceptanceService;
use OpenPhonebook\UserManagement\InvitationRepository;
use OpenPhonebook\UserManagement\InvitationService;
use OpenPhonebook\UserManagement\InvitationSession;
use OpenPhonebook\UserManagement\UserAdministrationRepository;
use OpenPhonebook\Web\InvitationAcceptView;
use OpenPhonebook\Web\Request;
use OpenPhonebook\Web\SecurityHeaders;
use OpenPhonebook\Web\WebRuntime;
use Throwable;

$projectRoot = dirname(__DIR__, 3);
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

$translator = $runtime->translator();
$session = new InvitationSession();
$service = new InvitationService(
    new InvitationRepository($runtime->database),
    new UserAdministrationRepository($runtime->database),
    new SystemClock(),
    $runtime->applicationKey,
);

$queryToken = $_GET['token'] ?? null;

if (is_string($queryToken) && $queryToken !== '') {
    $invitation = $service->resolve($queryToken);

    if ($invitation === null) {
        http_response_code(404);
        SecurityHeaders::sendHtml();
        echo InvitationAcceptView::invalid($translator);
        exit;
    }

    $session->store($queryToken);
    header('Location: /invite/accept/', true, 303);
    exit;
}

$storedToken = $session->token();
$invitation = $storedToken === null ? null : $service->resolve($storedToken);

if ($invitation === null) {
    http_response_code(404);
    SecurityHeaders::sendHtml();
    echo InvitationAcceptView::invalid($translator);
    exit;
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CsrfToken::verify(Request::postString('_csrf'))) {
        http_response_code(400);
        $error = $translator->get('error.request');
    } else {
        $password = Request::postPassword('password');
        $confirmation = Request::postPassword('password_confirm');

        if ($password !== $confirmation) {
            $error = $translator->get('error.request');
        } else {
            try {
                $acceptance = new InvitationAcceptanceService(
                    $runtime->database,
                    new InvitationRepository($runtime->database),
                    new UserAdministrationRepository($runtime->database),
                );
                $acceptance->accept(
                    $invitation,
                    Request::postString('display_name'),
                    $password,
                    Request::postString('locale'),
                    Request::postString('timezone')
                );

                $session->clear();
                CsrfToken::rotate();

                SecurityHeaders::sendHtml();
                echo InvitationAcceptView::complete($translator);
                exit;
            } catch (Throwable) {
                $error = $translator->get('error.request');
            }
        }
    }
}

SecurityHeaders::sendHtml();

echo InvitationAcceptView::render(
    $translator,
    $invitation,
    CsrfToken::issue(),
    $error
);
