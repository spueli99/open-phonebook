<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\I18n\Translator;

final class LoginView
{
    public static function render(
        Translator $translator,
        string $csrfToken,
        ?string $error = null
    ): string {
        $errorHtml = $error === null
            ? ''
            : '<p class="error" role="alert">' . self::escape($error) . '</p>';

        return '<!doctype html>'
            . '<html lang="' . self::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>' . self::escape($translator->get('auth.login.title'))
            . ' · Open Phonebook</title>'
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;max-width:420px;margin:10vh auto;padding:20px}'
            . 'label{display:block;margin-top:16px;font-weight:600}'
            . 'input{box-sizing:border-box;width:100%;padding:10px;margin-top:6px}'
            . 'button{margin-top:20px;padding:10px 16px;font-weight:700}'
            . '.error{padding:10px;border:1px solid currentColor;border-radius:6px}'
            . '</style></head><body><main>'
            . '<h1>' . self::escape($translator->get('auth.login.title')) . '</h1>'
            . $errorHtml
            . '<form method="post" autocomplete="on">'
            . '<input type="hidden" name="_csrf" value="' . self::escape($csrfToken) . '">'
            . '<label>' . self::escape($translator->get('auth.login.email'))
            . '<input type="email" name="email" required autocomplete="username"></label>'
            . '<label>' . self::escape($translator->get('auth.login.password'))
            . '<input type="password" name="password" required autocomplete="current-password"></label>'
            . '<button type="submit">' . self::escape($translator->get('auth.login.submit'))
            . '</button></form><p><a href="/password-reset/request/">'
            . self::escape(
                $translator->get('auth.password_reset.forgot')
            )
            . '</a></p></main></body></html>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function __construct()
    {
    }
}
