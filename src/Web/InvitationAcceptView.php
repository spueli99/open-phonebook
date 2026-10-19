<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\I18n\Translator;
use OpenPhonebook\UserManagement\Invitation;

final class InvitationAcceptView
{
    public static function render(
        Translator $translator,
        Invitation $invitation,
        string $csrfToken,
        ?string $error = null
    ): string {
        $errorHtml = $error === null
            ? ''
            : '<p class="error" role="alert">' . Html::escape($error) . '</p>';

        return '<!doctype html>'
            . '<html lang="' . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>' . Html::escape($translator->get('invite.accept.title'))
            . ' · Open Phonebook</title>'
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;max-width:520px;margin:7vh auto;padding:20px}'
            . 'label{display:block;margin-top:16px;font-weight:600}'
            . 'input,select{box-sizing:border-box;width:100%;padding:10px;margin-top:6px}'
            . 'button{margin-top:20px;padding:10px 16px;font-weight:700}'
            . '.error{padding:10px;border:1px solid currentColor;border-radius:6px}'
            . '</style></head><body><main>'
            . '<h1>' . Html::escape($translator->get('invite.accept.title')) . '</h1>'
            . '<p>' . Html::escape($translator->get('invite.accept.for'))
            . ' <strong>' . Html::escape($invitation->email) . '</strong></p>'
            . $errorHtml
            . '<form method="post">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken) . '">'
            . '<label>' . Html::escape($translator->get('users.name'))
            . '<input name="display_name" required autocomplete="name"></label>'
            . '<label>' . Html::escape($translator->get('auth.login.password'))
            . '<input type="password" name="password" required minlength="12" '
            . 'autocomplete="new-password"></label>'
            . '<label>' . Html::escape($translator->get('invite.accept.password_again'))
            . '<input type="password" name="password_confirm" required minlength="12" '
            . 'autocomplete="new-password"></label>'
            . '<label>' . Html::escape($translator->get('invite.accept.language'))
            . '<select name="locale"><option value="en">English</option>'
            . '<option value="de">Deutsch</option></select></label>'
            . '<label>' . Html::escape($translator->get('invite.accept.timezone'))
            . '<select name="timezone"><option value="Europe/Berlin">Europe/Berlin</option>'
            . '<option value="UTC">UTC</option></select></label>'
            . '<button type="submit">'
            . Html::escape($translator->get('invite.accept.submit'))
            . '</button></form></main></body></html>';
    }

    public static function invalid(Translator $translator): string
    {
        return '<!doctype html>'
            . '<html lang="' . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>' . Html::escape($translator->get('invite.invalid.title'))
            . ' · Open Phonebook</title></head><body><main>'
            . '<h1>' . Html::escape($translator->get('invite.invalid.title')) . '</h1>'
            . '<p>' . Html::escape($translator->get('invite.invalid.message')) . '</p>'
            . '</main></body></html>';
    }

    public static function complete(Translator $translator): string
    {
        return '<!doctype html>'
            . '<html lang="' . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>' . Html::escape($translator->get('invite.complete.title'))
            . ' · Open Phonebook</title></head><body><main>'
            . '<h1>' . Html::escape($translator->get('invite.complete.title')) . '</h1>'
            . '<p>' . Html::escape($translator->get('invite.complete.message')) . '</p>'
            . '<p><a href="/login/">'
            . Html::escape($translator->get('auth.login.title')) . '</a></p>'
            . '</main></body></html>';
    }

    private function __construct()
    {
    }
}
