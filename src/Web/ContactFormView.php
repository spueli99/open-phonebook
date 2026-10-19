<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Contacts\Contact;
use OpenPhonebook\I18n\Translator;

final class ContactFormView
{
    public static function render(
        Translator $translator,
        int $addressBookId,
        string $csrfToken,
        ?Contact $contact = null
    ): string {
        $isEdit = $contact !== null;
        $action = $isEdit ? '/contacts/update/' : '/contacts/create/';
        $title = $translator->get($isEdit ? 'contacts.edit' : 'contacts.create');

        $phoneRows = '';

        if ($contact !== null) {
            foreach ($contact->phoneNumbers as $phoneNumber) {
                $phoneRows .= self::phoneRow(
                    $translator,
                    $phoneNumber->label ?? '',
                    $phoneNumber->rawNumber
                );
            }
        }

        if ($phoneRows === '') {
            for ($index = 0; $index < 3; $index++) {
                $phoneRows .= self::phoneRow($translator, '', '');
            }
        }

        return '<!doctype html><html lang="' . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . Html::escape($title) . ' · Open Phonebook</title>'
            . '<style>body{font-family:system-ui,sans-serif;max-width:800px;margin:5vh auto;padding:20px}'
            . 'form{display:grid;gap:12px}label{display:grid;gap:4px}input,textarea,button{padding:8px}'
            . '.phone{display:grid;grid-template-columns:1fr 2fr;gap:8px}</style></head><body><main>'
            . '<nav><a href="/address-books/view/?id=' . $addressBookId . '">'
            . Html::escape($translator->get('contacts.back')) . '</a></nav>'
            . '<h1>' . Html::escape($title) . '</h1>'
            . '<form method="post" action="' . $action . '">'
            . '<input type="hidden" name="_csrf" value="' . Html::escape($csrfToken) . '">'
            . '<input type="hidden" name="address_book_id" value="' . $addressBookId . '">'
            . ($contact === null ? '' : '<input type="hidden" name="contact_id" value="' . $contact->id . '">')
            . self::field($translator, 'contacts.display_name', 'display_name', $contact?->displayName ?? '', 255, true)
            . self::field($translator, 'contacts.given_name', 'given_name', $contact?->givenName ?? '', 120)
            . self::field($translator, 'contacts.family_name', 'family_name', $contact?->familyName ?? '', 120)
            . self::field($translator, 'contacts.organization', 'organization_name', $contact?->organizationName ?? '', 255)
            . '<label>' . Html::escape($translator->get('contacts.notes'))
            . '<textarea name="notes" rows="5">' . Html::escape($contact?->notes ?? '') . '</textarea></label>'
            . '<fieldset><legend>' . Html::escape($translator->get('contacts.phone_numbers')) . '</legend>'
            . $phoneRows
            . '<p>' . Html::escape($translator->get('contacts.phone_numbers.help')) . '</p></fieldset>'
            . '<button type="submit">' . Html::escape($translator->get('contacts.save')) . '</button>'
            . '</form></main></body></html>';
    }

    private static function phoneRow(
        Translator $translator,
        string $label,
        string $number
    ): string {
        return '<div class="phone">'
            . '<input name="phone_labels[]" maxlength="64" placeholder="'
            . Html::escape($translator->get('contacts.phone_label'))
            . '" value="' . Html::escape($label) . '">'
            . '<input name="phone_numbers[]" maxlength="100" placeholder="'
            . Html::escape($translator->get('contacts.phone_number'))
            . '" value="' . Html::escape($number) . '">'
            . '</div>';
    }

    private static function field(
        Translator $translator,
        string $labelKey,
        string $name,
        string $value,
        int $maxLength,
        bool $required = false
    ): string {
        return '<label>' . Html::escape($translator->get($labelKey))
            . '<input name="' . Html::escape($name) . '" maxlength="' . $maxLength . '"'
            . ($required ? ' required' : '')
            . ' value="' . Html::escape($value) . '"></label>';
    }

    private function __construct()
    {
    }
}
