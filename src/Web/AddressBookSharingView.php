<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Contacts\AddressBook;
use OpenPhonebook\Contacts\AddressBookShare;
use OpenPhonebook\I18n\Translator;

final class AddressBookSharingView
{
    /**
     * @param list<AddressBookShare> $shares
     */
    public static function render(
        Translator $translator,
        AddressBook $addressBook,
        array $shares,
        string $csrfToken
    ): string {
        $rows = '';

        foreach ($shares as $share) {
            $rows .= '<tr><td>'
                . Html::escape($share->displayName)
                . '</td><td>'
                . Html::escape($share->email)
                . '</td><td>'
                . Html::escape(
                    $translator->get(
                        'contacts.access.' . $share->accessLevel->value
                    )
                )
                . '</td><td>'
                . '<form method="post" action="/address-books/sharing/revoke/">'
                . '<input type="hidden" name="_csrf" value="'
                . Html::escape($csrfToken) . '">'
                . '<input type="hidden" name="address_book_id" value="'
                . $addressBook->id . '">'
                . '<input type="hidden" name="user_id" value="'
                . $share->userId . '">'
                . '<button type="submit">'
                . Html::escape(
                    $translator->get('contacts.sharing.revoke')
                )
                . '</button></form></td></tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="4">'
                . Html::escape($translator->get('contacts.sharing.none'))
                . '</td></tr>';
        }

        return '<!doctype html><html lang="'
            . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>'
            . Html::escape($translator->get('contacts.sharing.title'))
            . ' · Open Phonebook</title>'
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;max-width:1000px;margin:5vh auto;padding:20px}'
            . 'table{width:100%;border-collapse:collapse}'
            . 'th,td{padding:10px;border-bottom:1px solid #bbb;text-align:left;vertical-align:top}'
            . 'form.share{display:flex;gap:8px;flex-wrap:wrap;margin:20px 0}'
            . 'input,select,button{padding:8px}</style>'
            . '<link rel="stylesheet" href="/assets/app.css"></head><body>' . AppChrome::header() . '<main><nav>'
            . '<a href="/address-books/view/?id='
            . $addressBook->id . '">'
            . Html::escape($translator->get('contacts.back'))
            . '</a></nav>'
            . '<h1>'
            . Html::escape($translator->get('contacts.sharing.title'))
            . ': ' . Html::escape($addressBook->name)
            . '</h1>'
            . '<form class="share" method="post" '
            . 'action="/address-books/sharing/add/">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken) . '">'
            . '<input type="hidden" name="address_book_id" value="'
            . $addressBook->id . '">'
            . '<label>'
            . Html::escape($translator->get('contacts.sharing.email'))
            . ' <input type="email" name="email" required></label>'
            . '<label>'
            . Html::escape($translator->get('contacts.access.title'))
            . ' <select name="access_level">'
            . '<option value="read">'
            . Html::escape($translator->get('contacts.access.read'))
            . '</option>'
            . '<option value="write">'
            . Html::escape($translator->get('contacts.access.write'))
            . '</option></select></label>'
            . '<button type="submit">'
            . Html::escape($translator->get('contacts.sharing.save'))
            . '</button></form>'
            . '<table><thead><tr><th>'
            . Html::escape($translator->get('users.name'))
            . '</th><th>'
            . Html::escape($translator->get('users.email'))
            . '</th><th>'
            . Html::escape($translator->get('contacts.access.title'))
            . '</th><th>'
            . Html::escape($translator->get('users.actions'))
            . '</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table></main>' . AppChrome::footer() . '</body></html>';
    }

    private function __construct()
    {
    }
}
