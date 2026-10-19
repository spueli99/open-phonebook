<?php

declare(strict_types=1);

use OpenPhonebook\Auth\Authorization;
use OpenPhonebook\Auth\Permission;
use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Setup\InstallationState;
use OpenPhonebook\UserManagement\InvitationAdministrationService;
use OpenPhonebook\UserManagement\InvitationRepository;
use OpenPhonebook\UserManagement\UserAdministrationRepository;
use OpenPhonebook\Web\SecurityHeaders;
use OpenPhonebook\Web\UserManagementView;
use OpenPhonebook\Web\WebRuntime;
use Throwable;

$projectRoot = dirname(__DIR__, 3);
$autoload = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
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
    exit;
}

$user = $runtime->userSession->user();

if ($user === null) {
    header('Location: /login/', true, 302);
    exit;
}

$translator = $runtime->translator($user->locale);
$authorization = new Authorization();

if (!$authorization->allows($user, Permission::ManageUsers)) {
    http_response_code(403);
    SecurityHeaders::sendHtml();
    echo '<p>' . htmlspecialchars(
        $translator->get('error.forbidden'),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . '</p>';
    exit;
}

$userRepository = new UserAdministrationRepository($runtime->database);
$invitationService = new InvitationAdministrationService(
    new InvitationRepository($runtime->database),
    new SystemClock(),
    $runtime->applicationKey,
);

SecurityHeaders::sendHtml();

echo UserManagementView::render(
    $translator,
    $user,
    $userRepository->all(),
    $invitationService->allUsable(),
    CsrfToken::issue()
);
