<?php

declare(strict_types=1);

use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\AddressBookShareRepository;
use OpenPhonebook\Contacts\AddressBookSharingService;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\AddressBookSharingView;
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

$addressBookId = isset($_GET['id']) && is_string($_GET['id'])
    ? (int) $_GET['id']
    : 0;
$access = new AddressBookAccessService(
    new AddressBookAccessRepository($runtime->database)
);
$sharing = new AddressBookSharingService(
    $access,
    new AddressBookShareRepository($runtime->database)
);

try {
    $addressBook = $access->requireOwner(
        $addressBookId,
        $user->id
    )->addressBook;
    $shares = $sharing->allShares($addressBookId, $user->id);
} catch (Throwable) {
    http_response_code(404);
    exit;
}

SecurityHeaders::sendHtml();

echo AddressBookSharingView::render(
    $runtime->translator($user->locale),
    $addressBook,
    $shares,
    CsrfToken::issue()
);
