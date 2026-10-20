<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\I18n\Translator;

final class InvitationCreateView
{
    public static function render(
        Translator $translator,
        string $csrfToken,
        ?string $error = null,
        bool $mailSent = false
    ): string {
        $result = '';

        if ($mailSent) {
            $result = '<div class="result" role="status">'
                . '<p><strong>'
                . Html::escape($translator->get('users.invite.created'))
                . '</strong></p>'
                . '<p>'
                . Html::escape($translator->get('users.invite.sent'))
                . '</p></div>';
        }

        $errorHtml = $error === null
            ? ''
            : '<p class="error" role="alert">' . Html::escape($error) . '</p>';

        return '<!doctype html>'
            . '<html lang="' . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . Html::escape($translator->get('users.invite.title'))
            . ' · Open Phonebook</title>'
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;max-width:620px;margin:7vh auto;padding:20px}'
            . 'label{display:block;margin-top:16px;font-weight:600}'
            . 'input,select{box-sizing:border-box;width:100%;padding:10px;margin-top:6px}'
            . 'button{margin-top:20px;padding:10px 16px;font-weight:700}'
            . '.error,.result{padding:12px;border:1px solid currentColor;border-radius:6px}'
            . '</style></head><body><main>'
            . '<p><a href="/admin/users/">← '
            . Html::escape($translator->get('users.back')) . '</a></p>'
            . '<h1>' . Html::escape($translator->get('users.invite.title')) . '</h1>'
            . $errorHtml . $result
            . '<form method="post">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken) . '">'
            . '<label>' . Html::escape($translator->get('users.email'))
            . '<input type="email" name="email" required autocomplete="off"></label>'
            . '<label>' . Html::escape($translator->get('users.role'))
            . '<select name="role">'
            . '<option value="member">'
            . Html::escape($translator->get('role.member')) . '</option>'
            . '<option value="system_admin">'
            . Html::escape($translator->get('role.system_admin')) . '</option>'
            . '</select></label>'
            . '<button type="submit">'
            . Html::escape($translator->get('users.invite.create'))
            . '</button></form>'
            . '</main></body></html>';
    }

    private function __construct()
    {
    }
}
