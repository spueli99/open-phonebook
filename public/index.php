<?php

declare(strict_types=1);

use OpenPhonebook\Auth\Authorization;
use OpenPhonebook\Auth\Permission;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Setup\InstallationState;
use OpenPhonebook\Web\DashboardView;
use OpenPhonebook\Web\SecurityHeaders;
use OpenPhonebook\Web\WebRuntime;
use Throwable;

$projectRoot = dirname(__DIR__);
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

$user = $runtime->userSession->user();

if ($user === null) {
    header('Location: /login/', true, 302);
    exit;
}

$translator = $runtime->translator($user->locale);
$authorization = new Authorization();

SecurityHeaders::sendHtml();

echo DashboardView::render(
    $translator,
    $user,
    CsrfToken::issue(),
    $authorization->allows($user, Permission::ManageUsers),
    $authorization->allows($user, Permission::ViewOperations)
);
