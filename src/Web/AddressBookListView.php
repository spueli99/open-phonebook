<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Contacts\AddressBookAccess;
use OpenPhonebook\I18n\Translator;

final class AddressBookListView
{
    /**
     * @param list<AddressBookAccess> $addressBooks
     */
    public static function render(
        Translator $translator,
        array $addressBooks,
        string $csrfToken
    ): string {
        $rows = '';

        foreach ($addressBooks as $access) {
            $addressBook = $access->addressBook;
            $actions = '';

            if ($access->isOwner) {
                $actions .= '<form method="post" action="/address-books/rename/">'
                    . '<input type="hidden" name="_csrf" value="'
                    . Html::escape($csrfToken) . '">'
                    . '<input type="hidden" name="address_book_id" value="'
                    . $addressBook->id . '">'
                    . '<input name="name" maxlength="120" required value="'
                    . Html::escape($addressBook->name) . '">'
                    . '<button type="submit">'
                    . Html::escape(
                        $translator->get('contacts.address_books.rename')
                    )
                    . '</button></form>'
                    . '<a class="button" href="/address-books/sharing/?id='
                    . $addressBook->id . '">'
                    . Html::escape(
                        $translator->get('contacts.address_books.sharing')
                    )
                    . '</a>'
                    . '<form method="post" action="/address-books/delete/">'
                    . '<input type="hidden" name="_csrf" value="'
                    . Html::escape($csrfToken) . '">'
                    . '<input type="hidden" name="address_book_id" value="'
                    . $addressBook->id . '">'
                    . '<button type="submit">'
                    . Html::escape(
                        $translator->get('contacts.address_books.delete')
                    )
                    . '</button></form>';
            }

            $accessLabel = $access->isOwner
                ? $translator->get('contacts.access.owner')
                : $translator->get(
                    'contacts.access.' . $access->accessLevel->value
                );

            $rows .= '<tr>'
                . '<td><a href="/address-books/view/?id='
                . $addressBook->id . '">'
                . Html::escape($addressBook->name) . '</a></td>'
                . '<td>' . Html::escape($accessLabel) . '</td>'
                . '<td class="actions">' . $actions . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="3">'
                . Html::escape(
                    $translator->get('contacts.address_books.none')
                )
                . '</td></tr>';
        }

        return '<!doctype html><html lang="'
            . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>'
            . Html::escape($translator->get('contacts.address_books.title'))
            . ' · Open Phonebook</title>'
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;max-width:1100px;margin:5vh auto;padding:20px}'
            . 'table{width:100%;border-collapse:collapse}'
            . 'th,td{padding:10px;border-bottom:1px solid #bbb;text-align:left;vertical-align:top}'
            . '.actions{display:flex;gap:8px;flex-wrap:wrap}'
            . '.actions form{margin:0;display:flex;gap:6px}'
            . '.create{display:flex;gap:8px;margin:20px 0}'
            . 'input,button,a.button{padding:8px}</style>'
            . '</head><body><main><nav><a href="/">'
            . Html::escape($translator->get('nav.dashboard')) . '</a></nav>'
            . '<h1>'
            . Html::escape($translator->get('contacts.address_books.title'))
            . '</h1>'
            . '<form class="create" method="post" action="/address-books/create/">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken) . '">'
            . '<label>'
            . Html::escape($translator->get('contacts.address_books.name'))
            . ' <input name="name" maxlength="120" required></label>'
            . '<button type="submit">'
            . Html::escape(
                $translator->get('contacts.address_books.create')
            )
            . '</button></form>'
            . '<table><thead><tr><th>'
            . Html::escape($translator->get('contacts.address_books.name'))
            . '</th><th>'
            . Html::escape($translator->get('contacts.access.title'))
            . '</th><th>'
            . Html::escape($translator->get('users.actions'))
            . '</th></tr></thead><tbody>'
            . $rows . '</tbody></table></main></body></html>';
    }

    private function __construct()
    {
    }
}
