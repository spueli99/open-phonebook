<?php

declare(strict_types=1);

use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\ContactRepository;
use OpenPhonebook\Database\PdoTransactionManager;
use OpenPhonebook\Import\ContactImportService;
use OpenPhonebook\Import\ImportDuplicateDetector;
use OpenPhonebook\Import\CsvContactImportParser;
use OpenPhonebook\Import\SessionContactImportDraftStore;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\ContactImportPreviewView;
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
    $addressBook = $access->requireWritable(
        $addressBookId,
        $user->id
    )->addressBook;
    $contactRepository = new ContactRepository($runtime->database);
    $normalizer = $runtime->phoneNumberNormalizer();
    $result = (new ContactImportService(
        $access,
        new CsvContactImportParser(),
        new SessionContactImportDraftStore(
            new SystemClock(),
            $runtime->applicationKey
        ),
        $contactRepository,
        $normalizer,
        new ImportDuplicateDetector(
            $contactRepository,
            $normalizer
        ),
        new PdoTransactionManager($runtime->database)
    ))->import(
        Request::postString('draft_token'),
        $addressBookId,
        $user->id,
        Request::postIntList('force_duplicate_rows')
    );
} catch (Throwable) {
    http_response_code(400);
    SecurityHeaders::sendHtml();

    try {
        $addressBook = $access->requireWritable(
            $addressBookId,
            $user->id
        )->addressBook;
    } catch (Throwable) {
        exit;
    }

    $translator = $runtime->translator($user->locale);

    echo ContactImportPreviewView::renderUpload(
        $translator,
        $addressBook,
        CsrfToken::issue(),
        $translator->get('imports.error.confirm')
    );
    exit;
}

CsrfToken::rotate();
SecurityHeaders::sendHtml();

echo ContactImportPreviewView::renderComplete(
    $runtime->translator($user->locale),
    $addressBook,
    $result->importedContacts,
    $result->skippedDuplicates
);
