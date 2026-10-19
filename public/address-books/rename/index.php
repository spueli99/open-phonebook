<?php

declare(strict_types=1);

use OpenPhonebook\Contacts\AddressBookRepository;
use OpenPhonebook\Contacts\AddressBookService;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\Request;
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!CsrfToken::verify(Request::postString('_csrf'))) {
    http_response_code(400);
    exit;
}

try {
    $service = new AddressBookService(
        new AddressBookRepository($runtime->database)
    );
    $service->rename(
        (int) Request::postString('address_book_id'),
        $user->id,
        Request::postString('name')
    );
} catch (Throwable) {
    http_response_code(400);
    exit;
}

CsrfToken::rotate();
header('Location: /address-books/', true, 303);
