<?php

declare(strict_types=1);

use OpenPhonebook\Auth\Authorization;
use OpenPhonebook\Auth\Permission;
use OpenPhonebook\Auth\Role;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\UserManagement\UserAdministrationRepository;
use OpenPhonebook\UserManagement\UserAdministrationService;
use OpenPhonebook\Web\Request;
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

if (!(new Authorization())->allows($user, Permission::ManageUsers)) {
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
    $role = Role::from(Request::postString('role'));
    $service = new UserAdministrationService(
        new UserAdministrationRepository($runtime->database)
    );
    $service->setRole(
        (int) Request::postString('user_id'),
        $role,
        $user->id
    );
} catch (Throwable) {
    http_response_code(400);
    exit;
}

CsrfToken::rotate();
header('Location: /admin/users/', true, 303);
