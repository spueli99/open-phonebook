<?php

declare(strict_types=1);

use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Sources\ContactSourceRepository;
use OpenPhonebook\Web\GoogleSourceView;
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

$addressBookId = isset($_GET['address_book_id'])
    && is_string($_GET['address_book_id'])
    ? (int) $_GET['address_book_id']
    : 0;

$access = new AddressBookAccessService(
    new AddressBookAccessRepository($runtime->database)
);

try {
    $addressBook = $access->requireOwner(
        $addressBookId,
        $user->id
    )->addressBook;
} catch (Throwable) {
    http_response_code(404);
    exit;
}

$source = (new ContactSourceRepository(
    $runtime->database
))->findGoogleForAddressBook(
    $user->id,
    $addressBookId
);

SecurityHeaders::sendHtml();

echo GoogleSourceView::render(
    $runtime->translator($user->locale),
    $addressBook,
    $source !== null,
    CsrfToken::issue()
);
