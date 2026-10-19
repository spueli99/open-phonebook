<?php

declare(strict_types=1);

use OpenPhonebook\Contacts\AddressBookRepository;
use OpenPhonebook\Contacts\AddressBookService;
use OpenPhonebook\Contacts\ContactRepository;
use OpenPhonebook\Contacts\ContactService;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\ContactListView;
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

$addressBookId = isset($_GET['id']) && is_string($_GET['id'])
    ? (int) $_GET['id']
    : 0;

$addressBooks = new AddressBookRepository($runtime->database);
$addressBookService = new AddressBookService($addressBooks);

try {
    $addressBook = $addressBookService->getOwned($addressBookId, $user->id);
} catch (Throwable) {
    http_response_code(404);
    exit;
}

$service = new ContactService(
    $addressBooks,
    new ContactRepository($runtime->database)
);
$searchQuery = isset($_GET['q']) && is_string($_GET['q'])
    ? trim($_GET['q'])
    : '';

try {
    $contacts = $service->searchInOwnedAddressBook(
        $addressBookId,
        $user->id,
        $searchQuery
    );
} catch (Throwable) {
    http_response_code(400);
    exit;
}

SecurityHeaders::sendHtml();

echo ContactListView::render(
    $runtime->translator($user->locale),
    $addressBook,
    $contacts,
    CsrfToken::issue(),
    $searchQuery
);
