<?php

declare(strict_types=1);

use OpenPhonebook\Auth\PasswordResetSession;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\PasswordResetView;
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

if ($runtime->userSession->isAuthenticated()) {
    header('Location: /', true, 302);
    exit;
}

$translator = $runtime->translator();
$submitted = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $startedAt = hrtime(true);

    if (CsrfToken::verify(Request::postString('_csrf'))) {
        (new PasswordResetSession())->clear();

        try {
            $runtime->passwordResetService()->request(
                Request::postString('email')
            );
        } catch (Throwable) {
            // Keep the public response generic for all request outcomes.
        }

        CsrfToken::rotate();
        $submitted = true;
    } else {
        http_response_code(400);
    }

    $elapsedNanoseconds = hrtime(true) - $startedAt;
    $minimumNanoseconds = 750_000_000;

    if ($elapsedNanoseconds < $minimumNanoseconds) {
        usleep(
            (int) (($minimumNanoseconds - $elapsedNanoseconds) / 1000)
        );
    }
}

SecurityHeaders::sendHtml();

echo PasswordResetView::request(
    $translator,
    CsrfToken::issue(),
    $submitted
);
