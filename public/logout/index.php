<?php

declare(strict_types=1);

use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Setup\InstallationState;
use OpenPhonebook\Web\Request;
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!CsrfToken::verify(Request::postString('_csrf'))) {
    http_response_code(400);
    exit;
}

$runtime->userSession->logout();
CsrfToken::rotate();

header('Location: /login/', true, 303);
