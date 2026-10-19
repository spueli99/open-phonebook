<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Contacts\AddressBook;
use OpenPhonebook\Google\GoogleContactsSyncResult;
use OpenPhonebook\I18n\Translator;

final class GoogleSourceView
{
    public static function render(
        Translator $translator,
        AddressBook $addressBook,
        bool $connected,
        string $csrfToken,
        ?GoogleContactsSyncResult $result = null
    ): string {
        $status = $connected
            ? $translator->get('sources.google.connected')
            : $translator->get('sources.google.not_connected');

        $resultHtml = '';

        if ($result !== null) {
            $resultHtml = '<p>'
                . Html::escape(
                    sprintf(
                        $translator->get('sources.google.sync_result'),
                        $result->created,
                        $result->updated,
                        $result->deleted,
                        $result->skipped
                    )
                )
                . '</p>';
        }

        $actions = $connected
            ? '<form method="post" action="/sources/google/sync/">'
                . '<input type="hidden" name="_csrf" value="'
                . Html::escape($csrfToken) . '">'
                . '<input type="hidden" name="address_book_id" value="'
                . $addressBook->id . '">'
                . '<button type="submit">'
                . Html::escape($translator->get('sources.google.sync'))
                . '</button></form>'
                . '<p><a href="/sources/google/connect/?address_book_id='
                . $addressBook->id . '">'
                . Html::escape($translator->get('sources.google.reconnect'))
                . '</a></p>'
            : '<p><a class="button" href="/sources/google/connect/?address_book_id='
                . $addressBook->id . '">'
                . Html::escape($translator->get('sources.google.connect'))
                . '</a></p>';

        return '<!doctype html><html lang="'
            . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>'
            . Html::escape($translator->get('sources.google.title'))
            . ' · Open Phonebook</title>'
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;max-width:800px;margin:5vh auto;padding:20px}'
            . 'button,a.button{padding:8px 12px}'
            . '</style></head><body><main>'
            . '<nav><a href="/address-books/view/?id='
            . $addressBook->id . '">'
            . Html::escape($translator->get('contacts.back'))
            . '</a></nav>'
            . '<h1>'
            . Html::escape($translator->get('sources.google.title'))
            . '</h1>'
            . '<p>' . Html::escape($addressBook->name) . '</p>'
            . '<p>' . Html::escape($status) . '</p>'
            . $resultHtml
            . $actions
            . '</main></body></html>';
    }

    private function __construct()
    {
    }
}
