<?php

declare(strict_types=1);

use OpenPhonebook\Auth\PasswordResetSession;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\PasswordResetView;
use OpenPhonebook\Web\Request;
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

$translator = $runtime->translator();
$session = new PasswordResetSession();
$service = $runtime->passwordResetService();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $method === 'POST'
    ? Request::postString('action')
    : '';

if ($method === 'POST' && $action === 'capture') {
    if (!CsrfToken::verify(Request::postString('_csrf'))) {
        http_response_code(400);
        exit;
    }

    $plainToken = Request::postString('token');
    $request = $service->resolve($plainToken);

    if ($request === null) {
        http_response_code(404);
        SecurityHeaders::sendHtml();
        echo PasswordResetView::invalid($translator);
        exit;
    }

    $session->store($plainToken);
    CsrfToken::rotate();

    header('Location: /password-reset/', true, 303);
    exit;
}

$storedToken = $session->token();

if ($storedToken === null) {
    SecurityHeaders::sendHtml();
    echo PasswordResetView::capture(
        $translator,
        CsrfToken::issue()
    );
    exit;
}

$request = $service->resolve($storedToken);

if ($request === null) {
    $session->clear();
    http_response_code(404);
    SecurityHeaders::sendHtml();
    echo PasswordResetView::invalid($translator);
    exit;
}

$error = null;

if ($method === 'POST' && $action === 'complete') {
    if (!CsrfToken::verify(Request::postString('_csrf'))) {
        http_response_code(400);
        $error = $translator->get('error.request');
    } else {
        $password = Request::postPassword('password');
        $confirmation = Request::postPassword('password_confirm');

        if ($password !== $confirmation) {
            $error = $translator->get(
                'auth.password_reset.password_mismatch'
            );
        } else {
            try {
                $service->complete($request, $password);
                $session->clear();
                CsrfToken::rotate();

                SecurityHeaders::sendHtml();
                echo PasswordResetView::complete($translator);
                exit;
            } catch (Throwable) {
                $error = $translator->get('error.request');
            }
        }
    }
}

SecurityHeaders::sendHtml();

echo PasswordResetView::reset(
    $translator,
    CsrfToken::issue(),
    $error
);
