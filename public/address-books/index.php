<?php

declare(strict_types=1);

use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\AddressBookListView;
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

$service = new AddressBookAccessService(
    new AddressBookAccessRepository($runtime->database)
);

SecurityHeaders::sendHtml();

echo AddressBookListView::render(
    $runtime->translator($user->locale),
    $service->allAccessibleBy($user->id),
    CsrfToken::issue()
);
