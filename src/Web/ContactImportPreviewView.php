<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Contacts\AddressBook;
use OpenPhonebook\I18n\Translator;
use OpenPhonebook\Import\ImportReviewResult;

final class ContactImportPreviewView
{
    public static function renderUpload(
        Translator $translator,
        AddressBook $addressBook,
        string $csrfToken,
        ?string $error = null
    ): string {
        $errorHtml = $error === null
            ? ''
            : '<p role="alert">' . Html::escape($error) . '</p>';

        return self::pageStart(
            $translator,
            $translator->get('imports.csv.title')
        )
            . '<nav><a href="/address-books/view/?id='
            . $addressBook->id . '">'
            . Html::escape($translator->get('contacts.back'))
            . '</a></nav>'
            . '<h1>' . Html::escape($translator->get('imports.csv.title'))
            . '</h1>'
            . $errorHtml
            . '<p>' . Html::escape($translator->get('imports.csv.help'))
            . '</p>'
            . '<form method="post" enctype="multipart/form-data">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken) . '">'
            . '<input type="hidden" name="address_book_id" value="'
            . $addressBook->id . '">'
            . '<input type="hidden" name="MAX_FILE_SIZE" value="2000000">'
            . '<input type="file" name="import_file" accept=".csv,text/csv" required>'
            . '<button type="submit">'
            . Html::escape($translator->get('imports.preview'))
            . '</button></form>'
            . '<p><code>display_name,given_name,family_name,'
            . 'organization_name,notes,phone_1,phone_1_label,'
            . 'phone_2,phone_2_label,phone_3,phone_3_label</code></p>'
            . self::pageEnd();
    }

    public static function renderPreview(
        Translator $translator,
        AddressBook $addressBook,
        ImportReviewResult $preview,
        string $csrfToken,
        string $draftToken
    ): string {
        $rows = '';

        foreach ($preview->candidates as $candidate) {
            $contact = $candidate->contact;
            $numbers = [];

            foreach ($contact->phoneNumbers as $number) {
                $label = $number->label === null
                    ? ''
                    : $number->label . ': ';
                $numbers[] = Html::escape($label . $number->rawNumber);
            }

            $statusKey = 'imports.duplicate.'
                . $candidate->duplicateStatus->value;
            $action = '';

            if ($candidate->duplicateStatus->isDuplicate()) {
                $action = '<select name="duplicate_actions['
                    . $candidate->sourceRow . ']">'
                    . '<option value="skip">'
                    . Html::escape(
                        $translator->get('imports.duplicate.skip')
                    )
                    . '</option>';

                if ($candidate->mergeTarget !== null) {
                    $action .= '<option value="merge">'
                        . Html::escape(
                            $translator->get('imports.duplicate.merge')
                        )
                        . ' → '
                        . Html::escape(
                            $candidate->mergeTarget->displayName
                        )
                        . '</option>';
                }

                $action .= '<option value="import">'
                    . Html::escape(
                        $translator->get('imports.duplicate.force')
                    )
                    . '</option></select>';
            }

            $rows .= '<tr><td>'
                . Html::escape($contact->displayName)
                . '</td><td>'
                . Html::escape($contact->organizationName ?? '')
                . '</td><td>'
                . implode('<br>', $numbers)
                . '</td><td>'
                . Html::escape($translator->get($statusKey))
                . '</td><td>' . $action . '</td></tr>';
        }

        $warnings = '';

        foreach ($preview->warnings as $warning) {
            $warnings .= '<li>' . Html::escape($warning) . '</li>';
        }

        $warningBlock = $warnings === ''
            ? ''
            : '<h2>' . Html::escape($translator->get('imports.warnings'))
                . '</h2><ul>' . $warnings . '</ul>';

        return self::pageStart(
            $translator,
            $translator->get('imports.preview')
        )
            . '<nav><a href="/address-books/import/?id='
            . $addressBook->id . '">'
            . Html::escape($translator->get('imports.back'))
            . '</a></nav>'
            . '<h1>' . Html::escape($translator->get('imports.preview'))
            . '</h1>'
            . '<p>' . Html::escape(
                $translator->get('imports.preview.confirm_help')
            )
            . '</p>'
            . $warningBlock
            . '<form method="post" action="/address-books/import/confirm/">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken) . '">'
            . '<input type="hidden" name="address_book_id" value="'
            . $addressBook->id . '">'
            . '<input type="hidden" name="draft_token" value="'
            . Html::escape($draftToken) . '">'
            . '<table><thead><tr><th>'
            . Html::escape($translator->get('contacts.display_name'))
            . '</th><th>'
            . Html::escape($translator->get('contacts.organization'))
            . '</th><th>'
            . Html::escape($translator->get('contacts.phone_numbers'))
            . '</th><th>'
            . Html::escape($translator->get('imports.duplicate.status'))
            . '</th><th>'
            . Html::escape($translator->get('imports.duplicate.action'))
            . '</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . '<button type="submit">'
            . Html::escape($translator->get('imports.confirm'))
            . '</button></form>'
            . self::pageEnd();
    }

    public static function renderComplete(
        Translator $translator,
        AddressBook $addressBook,
        int $importedContacts,
        int $mergedContacts,
        int $skippedDuplicates
    ): string {
        return self::pageStart(
            $translator,
            $translator->get('imports.complete.title')
        )
            . '<h1>'
            . Html::escape($translator->get('imports.complete.title'))
            . '</h1>'
            . '<p>'
            . Html::escape(
                sprintf(
                    $translator->get('imports.complete.count'),
                    $importedContacts
                )
            )
            . '</p>'
            . '<p>'
            . Html::escape(
                sprintf(
                    $translator->get('imports.complete.merged'),
                    $mergedContacts
                )
            )
            . '</p>'
            . '<p>'
            . Html::escape(
                sprintf(
                    $translator->get('imports.complete.skipped'),
                    $skippedDuplicates
                )
            )
            . '</p>'
            . '<p><a href="/address-books/view/?id='
            . $addressBook->id . '">'
            . Html::escape($translator->get('contacts.back'))
            . '</a></p>'
            . self::pageEnd();
    }

    private static function pageStart(
        Translator $translator,
        string $title
    ): string {
        return '<!doctype html><html lang="'
            . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . Html::escape($title)
            . ' · Open Phonebook</title>'
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;max-width:1000px;margin:5vh auto;padding:20px}'
            . 'form{display:grid;gap:12px;max-width:650px}'
            . 'input,button{padding:8px}'
            . 'table{width:100%;border-collapse:collapse}'
            . 'th,td{padding:10px;border-bottom:1px solid #bbb;text-align:left;vertical-align:top}'
            . '</style></head><body><main>';
    }

    private static function pageEnd(): string
    {
        return '</main></body></html>';
    }

    private function __construct()
    {
    }
}
