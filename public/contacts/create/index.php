<?php

declare(strict_types=1);

use OpenPhonebook\Contacts\AddressBookRepository;
use OpenPhonebook\Contacts\ContactRepository;
use OpenPhonebook\Contacts\ContactService;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\ContactFormInput;
use OpenPhonebook\Web\ContactFormView;
use OpenPhonebook\Web\Request;
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

$addressBooks = new AddressBookRepository($runtime->database);
$contacts = new ContactRepository($runtime->database);
$service = new ContactService($addressBooks, $contacts);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    if (!CsrfToken::verify(Request::postString('_csrf'))) {
        http_response_code(400);
        exit;
    }

    $addressBookId = (int) Request::postString('address_book_id');

    try {
        $service->create(
            $addressBookId,
            $user->id,
            Request::postString('display_name'),
            Request::postString('given_name'),
            Request::postString('family_name'),
            Request::postString('organization_name'),
            Request::postString('notes'),
            ContactFormInput::phoneNumbers()
        );
    } catch (Throwable) {
        http_response_code(400);
        exit;
    }

    CsrfToken::rotate();
    header('Location: /address-books/view/?id=' . $addressBookId, true, 303);
    exit;
}

if ($method !== 'GET') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}

$addressBookId = isset($_GET['address_book_id']) && is_string($_GET['address_book_id'])
    ? (int) $_GET['address_book_id']
    : 0;

if ($addressBooks->findOwnedBy($addressBookId, $user->id) === null) {
    http_response_code(404);
    exit;
}

SecurityHeaders::sendHtml();

echo ContactFormView::render(
    $runtime->translator($user->locale),
    $addressBookId,
    CsrfToken::issue()
);
