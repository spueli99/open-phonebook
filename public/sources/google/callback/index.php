<?php

declare(strict_types=1);

use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Google\GoogleContactsConnectionService;
use OpenPhonebook\Google\GoogleOAuthStateStore;
use OpenPhonebook\Sources\ContactSourceRepository;
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

$state = isset($_GET['state']) && is_string($_GET['state'])
    ? $_GET['state']
    : '';
$code = isset($_GET['code']) && is_string($_GET['code'])
    ? $_GET['code']
    : '';
$error = isset($_GET['error']) && is_string($_GET['error'])
    ? $_GET['error']
    : '';

if ($error !== '' || $state === '' || $code === '') {
    http_response_code(400);
    exit;
}

try {
    $oauthState = (new GoogleOAuthStateStore(
        new SystemClock(),
        $runtime->applicationKey
    ))->consume($state, $user->id);

    if ($oauthState === null) {
        throw new RuntimeException('Invalid Google OAuth state.');
    }

    $tokenSet = $runtime->googleOAuthClient()->exchangeCode(
        $code,
        $runtime->googleOAuthRedirectUri(),
        $oauthState['code_verifier']
    );

    if ($tokenSet->refreshToken === null) {
        throw new RuntimeException(
            'Google did not return a refresh token.'
        );
    }

    (new GoogleContactsConnectionService(
        new AddressBookAccessService(
            new AddressBookAccessRepository($runtime->database)
        ),
        new ContactSourceRepository($runtime->database),
        $runtime->secretCipher()
    ))->connect(
        $user->id,
        $oauthState['address_book_id'],
        $tokenSet->refreshToken
    );
} catch (Throwable) {
    http_response_code(400);
    exit;
}

header(
    'Location: /sources/google/?address_book_id='
        . $oauthState['address_book_id'],
    true,
    303
);
