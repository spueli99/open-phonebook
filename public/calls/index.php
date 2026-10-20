<?php

declare(strict_types=1);

use OpenPhonebook\Calls\CallHistoryRepository;
use OpenPhonebook\Web\CallHistoryView;
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

$before = filter_input(INPUT_GET, 'before', FILTER_VALIDATE_INT);

if ($before === false || (is_int($before) && $before < 1)) {
    http_response_code(400);
    exit;
}

try {
    $page = (new CallHistoryRepository(
        $runtime->database
    ))->recentOwnedBy(
        $user->id,
        is_int($before) ? $before : null,
        50
    );
} catch (Throwable) {
    http_response_code(503);
    exit;
}

SecurityHeaders::sendHtml();

echo CallHistoryView::render(
    $runtime->translator($user->locale),
    $page,
    $user->timezone
);
