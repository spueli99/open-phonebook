<?php

declare(strict_types=1);

use OpenPhonebook\Contacts\AddressBookAccessLevel;
use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\AddressBookShareRepository;
use OpenPhonebook\Contacts\AddressBookSharingService;
use OpenPhonebook\Security\CsrfToken;
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!CsrfToken::verify(Request::postString('_csrf'))) {
    http_response_code(400);
    exit;
}

$addressBookId = (int) Request::postString('address_book_id');

try {
    $sharing = new AddressBookSharingService(
        new AddressBookAccessService(
            new AddressBookAccessRepository($runtime->database)
        ),
        new AddressBookShareRepository($runtime->database)
    );
    $sharing->shareWithEmail(
        $addressBookId,
        $user->id,
        Request::postString('email'),
        AddressBookAccessLevel::from(
            Request::postString('access_level')
        )
    );
} catch (Throwable) {
    http_response_code(400);
    exit;
}

CsrfToken::rotate();
header(
    'Location: /address-books/sharing/?id=' . $addressBookId,
    true,
    303
);
