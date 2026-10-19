<?php

declare(strict_types=1);

use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Google\GoogleOAuthStateStore;
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

$addressBookId = isset($_GET['address_book_id'])
    && is_string($_GET['address_book_id'])
    ? (int) $_GET['address_book_id']
    : 0;

try {
    (new AddressBookAccessService(
        new AddressBookAccessRepository($runtime->database)
    ))->requireOwner($addressBookId, $user->id);

    $state = (new GoogleOAuthStateStore(
        new SystemClock(),
        $runtime->applicationKey
    ))->create($addressBookId, $user->id);

    $url = $runtime->googleOAuthClient()->authorizationUrl(
        $runtime->googleOAuthRedirectUri(),
        $state
    );
} catch (Throwable) {
    http_response_code(400);
    exit;
}

header('Location: ' . $url, true, 302);
