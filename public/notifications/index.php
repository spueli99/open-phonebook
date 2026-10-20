<?php

declare(strict_types=1);

use OpenPhonebook\Notifications\NotificationDeliveryHistoryFilter;
use OpenPhonebook\Notifications\NotificationDeliveryStatus;
use OpenPhonebook\Notifications\NotificationTrigger;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\NotificationManagementPage;
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

$user = $runtime->userSession->user();

if ($user === null) {
    header('Location: /login/', true, 302);
    exit;
}

$statusValue = Request::getString('status');
$triggerValue = Request::getString('trigger');
$channelValue = Request::getString('channel');
$beforeValue = Request::getString('before');

$status = $statusValue === ''
    ? null
    : NotificationDeliveryStatus::tryFrom($statusValue);
$trigger = $triggerValue === ''
    ? null
    : NotificationTrigger::tryFrom($triggerValue);
$channelId = $channelValue === ''
    ? null
    : Request::getPositiveInt('channel');
$beforeId = $beforeValue === ''
    ? null
    : Request::getPositiveInt('before');

if (
    ($statusValue !== '' && $status === null)
    || ($triggerValue !== '' && $trigger === null)
    || ($channelValue !== '' && $channelId === 0)
    || ($beforeValue !== '' && $beforeId === 0)
) {
    http_response_code(400);
    exit;
}

$historyFilter = new NotificationDeliveryHistoryFilter(
    $status,
    $trigger,
    $channelId,
    $beforeId
);

SecurityHeaders::sendHtml();

echo (new NotificationManagementPage(
    $runtime->notificationAdministrationService(),
    $runtime->translator($user->locale)
))->render(
    $user->id,
    $user->timezone,
    CsrfToken::issue(),
    $runtime->webPushPublicKey(),
    null,
    null,
    $historyFilter
);
