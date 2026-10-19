<?php

declare(strict_types=1);

use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Import\ContactImportPreviewService;
use OpenPhonebook\Import\SessionContactImportDraftStore;
use OpenPhonebook\Import\CsvContactImportParser;
use OpenPhonebook\Security\CsrfToken;
use OpenPhonebook\Web\ContactImportPreviewView;
use OpenPhonebook\Web\Request;
use OpenPhonebook\Web\SecurityHeaders;
use OpenPhonebook\Web\UploadedFile;
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

$access = new AddressBookAccessService(
    new AddressBookAccessRepository($runtime->database)
);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    if (!CsrfToken::verify(Request::postString('_csrf'))) {
        http_response_code(400);
        exit;
    }

    $addressBookId = (int) Request::postString('address_book_id');

    try {
        $addressBook = $access->requireWritable(
            $addressBookId,
            $user->id
        )->addressBook;
        $contents = UploadedFile::contents(
            'import_file',
            CsvContactImportParser::MAX_BYTES
        );
        $preview = (new ContactImportPreviewService(
            $access,
            new CsvContactImportParser()
        ))->preview(
            $addressBookId,
            $user->id,
            $contents
        );
        $draftToken = (new SessionContactImportDraftStore(
            new SystemClock(),
            $runtime->applicationKey
        ))->create(
            $addressBookId,
            $user->id,
            $contents
        );
    } catch (Throwable) {
        http_response_code(400);
        SecurityHeaders::sendHtml();

        try {
            $fallback = $access->requireWritable(
                $addressBookId,
                $user->id
            );
        } catch (Throwable) {
            exit;
        }

        $translator = $runtime->translator($user->locale);

        echo ContactImportPreviewView::renderUpload(
            $translator,
            $fallback->addressBook,
            CsrfToken::issue(),
            $translator->get('imports.error.invalid')
        );
        exit;
    }

    CsrfToken::rotate();
    SecurityHeaders::sendHtml();

    echo ContactImportPreviewView::renderPreview(
        $runtime->translator($user->locale),
        $addressBook,
        $preview,
        CsrfToken::issue(),
        $draftToken
    );
    exit;
}

if ($method !== 'GET') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}

$addressBookId = isset($_GET['id']) && is_string($_GET['id'])
    ? (int) $_GET['id']
    : 0;

try {
    $addressBook = $access->requireWritable(
        $addressBookId,
        $user->id
    )->addressBook;
} catch (Throwable) {
    http_response_code(404);
    exit;
}

SecurityHeaders::sendHtml();

echo ContactImportPreviewView::renderUpload(
    $runtime->translator($user->locale),
    $addressBook,
    CsrfToken::issue()
);
