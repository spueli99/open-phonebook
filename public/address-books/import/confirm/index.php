<?php

declare(strict_types=1);

use OpenPhonebook\Clock\SystemClock;
use OpenPhonebook\Contacts\AddressBookAccessRepository;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Contacts\ContactRepository;
use OpenPhonebook\Database\PdoTransactionManager;
use OpenPhonebook\Import\ContactImportFormat;
use OpenPhonebook\Import\ContactImportParserRegistry;
use OpenPhonebook\Import\ContactImportService;
use OpenPhonebook\Import\ImportContactMerger;
use OpenPhonebook\Import\ImportDuplicateAction;
use OpenPhonebook\Import\ImportDuplicateDecision;
use OpenPhonebook\Import\ImportDuplicateDetector;
use OpenPhonebook\Import\CsvContactImportParser;
use OpenPhonebook\Import\VCardContactImportParser;
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
    $duplicateDecisions = [];
    $mergeTargets = Request::postStringMap('merge_target_ids');

    foreach (Request::postStringMap('duplicate_actions') as $row => $value) {
        if (!preg_match('/^\d+$/', $row)) {
            throw new RuntimeException(
                'Invalid duplicate import row.'
            );
        }

        $rowIndex = (int) $row;

        if (isset($duplicateDecisions[$rowIndex])) {
            throw new RuntimeException(
                'Duplicate import row decision.'
            );
        }

        $action = ImportDuplicateAction::tryFrom($value);

        if ($action === null) {
            throw new RuntimeException(
                'Invalid duplicate import action.'
            );
        }

        $mergeTargetId = null;

        if ($action === ImportDuplicateAction::Merge) {
            $target = $mergeTargets[$row] ?? '';

            if (!preg_match('/^[1-9]\d*$/', $target)) {
                throw new RuntimeException(
                    'Invalid duplicate merge target.'
                );
            }

            $mergeTargetId = (int) $target;
        }

        $duplicateDecisions[$rowIndex] = new ImportDuplicateDecision(
            $action,
            $mergeTargetId
        );
    }

    $result = (new ContactImportService(
        $access,
        new ContactImportParserRegistry([
            ContactImportFormat::Csv->value
                => new CsvContactImportParser(),
            ContactImportFormat::VCard->value
                => new VCardContactImportParser(),
        ]),
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
        new ImportContactMerger($normalizer),
        new PdoTransactionManager($runtime->database)
    ))->import(
        Request::postString('draft_token'),
        $addressBookId,
        $user->id,
        $duplicateDecisions
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
    $result->mergedContacts,
    $result->skippedDuplicates
);
