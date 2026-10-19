<?php

declare(strict_types=1);

use OpenPhonebook\Auth\AuthenticationService;
use OpenPhonebook\Auth\LoginAttemptRepository;
use OpenPhonebook\Auth\LoginThrottle;
use OpenPhonebook\Auth\UserRepository;
use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Setup\InstallationState;
use OpenPhonebook\Web\LoginView;
use OpenPhonebook\Web\Request;
use OpenPhonebook\Web\SecurityHeaders;
use OpenPhonebook\Web\WebRuntime;
use Throwable;

$projectRoot = dirname(__DIR__, 2);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Open Phonebook dependencies are not installed.\n";
    exit;
}

require $autoload;

$installation = new InstallationState($projectRoot . '/storage/installed.lock');

if (!$installation->isInstalled()) {
    header('Location: /setup/', true, 302);
    exit;
}

try {
    $runtime = WebRuntime::boot($projectRoot);
} catch (Throwable) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Open Phonebook is temporarily unavailable.\n";
    exit;
}

if ($runtime->userSession->isAuthenticated()) {
    header('Location: /', true, 302);
    exit;
}

$translator = $runtime->translator();
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CsrfToken::verify(Request::postString('_csrf'))) {
        http_response_code(400);
        $error = $translator->get('auth.login.invalid');
    } else {
        $email = Request::postString('email');
        $password = Request::postPassword('password');
        $ipAddress = Request::clientIp();
        $throttle = new LoginThrottle(
            new LoginAttemptRepository($runtime->database),
            $runtime->applicationKey,
            new SystemClock(),
        );

        if ($throttle->isBlocked($email, $ipAddress)) {
            http_response_code(429);
            $error = $translator->get('auth.login.blocked');
        } else {
            $authentication = new AuthenticationService(
                new UserRepository($runtime->database)
            );
            $result = $authentication->authenticate($email, $password);

            if (!$result->successful || $result->user === null) {
                $throttle->registerFailure($email, $ipAddress);
                http_response_code(401);
                $error = $translator->get('auth.login.invalid');
            } else {
                $throttle->clear($email, $ipAddress);
                $runtime->userSession->login($result->user);
                CsrfToken::rotate();

                header('Location: /', true, 303);
                exit;
            }
        }
    }
}

SecurityHeaders::sendHtml();
echo LoginView::render($translator, CsrfToken::issue(), $error);
