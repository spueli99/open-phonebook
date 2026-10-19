<?php

declare(strict_types=1);

use OpenPhonebook\Contacts\AddressBookRepository;
use OpenPhonebook\Contacts\ContactService;
use OpenPhonebook\Contacts\ContactRepository;
use OpenPhonebook\Web\ContactFormView;
use OpenPhonebook\Security\CsrfToken;
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

$addressBookId = isset($_GET['address_book_id']) && is_string($_GET['address_book_id'])
    ? (int) $_GET['address_book_id']
    : 0;
$contactId = isset($_GET['contact_id']) && is_string($_GET['contact_id'])
    ? (int) $_GET['contact_id']
    : 0;

$addressBooks = new AddressBookRepository($runtime->database);
$service = new ContactService(
    $addressBooks,
    new ContactRepository($runtime->database),
    $runtime->phoneNumberNormalizer()
);

try {
    $contact = $service->getInOwnedAddressBook(
        $contactId,
        $addressBookId,
        $user->id
    );
} catch (Throwable) {
    http_response_code(404);
    exit;
}

SecurityHeaders::sendHtml();

echo ContactFormView::render(
    $runtime->translator($user->locale),
    $addressBookId,
    CsrfToken::issue(),
    $contact
);
