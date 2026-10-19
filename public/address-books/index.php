<?php

declare(strict_types=1);

use OpenPhonebook\Contacts\AddressBookRepository;
use OpenPhonebook\Contacts\AddressBookService;
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

$service = new AddressBookService(
    new AddressBookRepository($runtime->database)
);

SecurityHeaders::sendHtml();

echo AddressBookListView::render(
    $runtime->translator($user->locale),
    $service->allOwnedBy($user->id),
    CsrfToken::issue()
);
