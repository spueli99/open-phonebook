<?php

declare(strict_types=1);

use OpenPhonebook\Auth\Authorization;
use OpenPhonebook\Auth\Permission;
use OpenPhonebook\Web\AuditLogView;
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

$user = $runtime->userSession->user();

if ($user === null) {
    header('Location: /login/', true, 302);
    exit;
}

if (!(new Authorization())->allows($user, Permission::ViewAudit)) {
    http_response_code(403);
    exit;
}

try {
    $events = $runtime->auditEventRepository()->latest(100);
} catch (Throwable) {
    http_response_code(503);
    exit;
}

SecurityHeaders::sendHtml();

echo AuditLogView::render(
    $runtime->translator($user->locale),
    $events,
    $user->timezone
);
