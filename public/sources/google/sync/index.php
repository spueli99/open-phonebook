<?php

declare(strict_types=1);

use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\ContactRepository;
use OpenPhonebook\Database\PdoTransactionManager;
use OpenPhonebook\Google\GoogleContactsSyncService;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Sources\ContactSourceRepository;
use OpenPhonebook\Web\GoogleSourceView;
use OpenPhonebook\Web\Request;
use OpenPhonebook\Web\SecurityHeaders;
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
$access = new AddressBookAccessService(
    new AddressBookAccessRepository($runtime->database)
);

try {
    $addressBook = $access->requireOwner(
        $addressBookId,
        $user->id
    )->addressBook;
    $result = (new GoogleContactsSyncService(
        $access,
        new ContactSourceRepository($runtime->database),
        new ContactRepository($runtime->database),
        $runtime->phoneNumberNormalizer(),
        $runtime->secretCipher(),
        $runtime->googleOAuthClient(),
        $runtime->googlePeopleClient(),
        new PdoTransactionManager($runtime->database)
    ))->sync(
        $user->id,
        $addressBookId
    );
} catch (Throwable) {
    http_response_code(400);
    exit;
}

CsrfToken::rotate();
SecurityHeaders::sendHtml();

echo GoogleSourceView::render(
    $runtime->translator($user->locale),
    $addressBook,
    true,
    CsrfToken::issue(),
    $result
);
