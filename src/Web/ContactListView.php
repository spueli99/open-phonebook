<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Contacts\AddressBook;
use OpenPhonebook\Contacts\Contact;
use OpenPhonebook\I18n\Translator;

final class ContactListView
{
    /**
     * @param list<Contact> $contacts
     */
    public static function render(
        Translator $translator,
        AddressBook $addressBook,
        array $contacts,
        string $csrfToken,
        string $searchQuery = ''
    ): string {
        $rows = '';

        foreach ($contacts as $contact) {
            $numbers = [];

            foreach ($contact->phoneNumbers as $phoneNumber) {
                $prefix = $phoneNumber->label === null ? '' : $phoneNumber->label . ': ';
                $numbers[] = Html::escape($prefix . $phoneNumber->rawNumber);
            }

            $rows .= '<tr><td>'
                . '<a href="/contacts/edit/?address_book_id=' . $addressBook->id
                . '&contact_id=' . $contact->id . '">'
                . Html::escape($contact->displayName) . '</a></td>'
                . '<td>' . implode('<br>', $numbers) . '</td>'
                . '<td><form method="post" action="/contacts/delete/">'
                . '<input type="hidden" name="_csrf" value="' . Html::escape($csrfToken) . '">'
                . '<input type="hidden" name="address_book_id" value="' . $addressBook->id . '">'
                . '<input type="hidden" name="contact_id" value="' . $contact->id . '">'
                . '<button type="submit">' . Html::escape($translator->get('contacts.delete')) . '</button>'
                . '</form></td></tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="3">'
                . Html::escape($translator->get('contacts.none'))
                . '</td></tr>';
        }

        return '<!doctype html><html lang="' . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . Html::escape($addressBook->name) . ' · Open Phonebook</title>'
            . '<style>body{font-family:system-ui,sans-serif;max-width:1000px;margin:5vh auto;padding:20px}'
            . 'table{width:100%;border-collapse:collapse}'
            . 'th,td{padding:10px;border-bottom:1px solid #bbb;'
            . 'text-align:left;vertical-align:top}'
            . 'button,a.button{padding:8px 12px}</style></head><body><main>'
            . '<nav><a href="/address-books/">'
            . Html::escape($translator->get('contacts.address_books.title'))
            . '</a></nav>'
            . '<h1>' . Html::escape($addressBook->name) . '</h1>'
            . '<form method="get" action="/address-books/view/">'
            . '<input type="hidden" name="id" value="' . $addressBook->id . '">'
            . '<label>' . Html::escape($translator->get('contacts.search'))
            . ' <input name="q" maxlength="120" value="'
            . Html::escape($searchQuery) . '"></label>'
            . '<button type="submit">'
            . Html::escape($translator->get('contacts.search.submit'))
            . '</button></form>'
            . '<p><a class="button" href="/contacts/create/?address_book_id=' . $addressBook->id . '">'
            . Html::escape($translator->get('contacts.create')) . '</a></p>'
            . '<table><thead><tr><th>'
            . Html::escape($translator->get('contacts.display_name'))
            . '</th><th>'
            . Html::escape($translator->get('contacts.phone_numbers')) . '</th><th>'
            . Html::escape($translator->get('users.actions')) . '</th></tr></thead><tbody>'
            . $rows . '</tbody></table></main></body></html>';
    }

    private function __construct()
    {
    }
}
