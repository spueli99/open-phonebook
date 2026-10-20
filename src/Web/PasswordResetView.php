<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\I18n\Translator;

final class PasswordResetView
{
    public static function request(
        Translator $translator,
        string $csrfToken,
        bool $submitted = false
    ): string {
        $message = $submitted
            ? '<p>'
                . Html::escape(
                    $translator->get(
                        'auth.password_reset.requested'
                    )
                )
                . '</p>'
            : '';

        return self::pageStart(
            $translator,
            $translator->get('auth.password_reset.request_title')
        )
            . '<h1>'
            . Html::escape(
                $translator->get('auth.password_reset.request_title')
            )
            . '</h1>'
            . $message
            . '<p>'
            . Html::escape(
                $translator->get('auth.password_reset.request_help')
            )
            . '</p><form method="post">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken)
            . '"><label>'
            . Html::escape($translator->get('auth.login.email'))
            . '<input type="email" name="email" required '
            . 'autocomplete="username"></label>'
            . '<button type="submit">'
            . Html::escape(
                $translator->get('auth.password_reset.request_submit')
            )
            . '</button></form><p><a href="/login/">'
            . Html::escape($translator->get('auth.login.title'))
            . '</a></p>'
            . self::pageEnd();
    }

    public static function capture(
        Translator $translator,
        string $csrfToken
    ): string {
        return self::pageStart(
            $translator,
            $translator->get('auth.password_reset.title'),
            '<script src="/assets/password-reset-capture.js" defer></script>'
        )
            . '<h1>'
            . Html::escape($translator->get('auth.password_reset.title'))
            . '</h1><p id="password-reset-status" data-invalid="'
            . Html::escape(
                $translator->get('auth.password_reset.invalid')
            )
            . '">'
            . Html::escape(
                $translator->get('auth.password_reset.preparing')
            )
            . '</p><form id="password-reset-capture" method="post">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken)
            . '"><input type="hidden" name="action" value="capture">'
            . '<input type="hidden" name="token" '
            . 'id="password-reset-token" value=""></form>'
            . self::pageEnd();
    }

    public static function reset(
        Translator $translator,
        string $csrfToken,
        ?string $error = null
    ): string {
        $errorHtml = $error === null
            ? ''
            : '<p class="error" role="alert">'
                . Html::escape($error)
                . '</p>';

        return self::pageStart(
            $translator,
            $translator->get('auth.password_reset.title')
        )
            . '<h1>'
            . Html::escape($translator->get('auth.password_reset.title'))
            . '</h1>'
            . $errorHtml
            . '<form method="post">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken)
            . '"><input type="hidden" name="action" value="complete">'
            . '<label>'
            . Html::escape(
                $translator->get('auth.password_reset.new_password')
            )
            . '<input type="password" name="password" required '
            . 'minlength="12" autocomplete="new-password"></label>'
            . '<label>'
            . Html::escape(
                $translator->get(
                    'auth.password_reset.password_again'
                )
            )
            . '<input type="password" name="password_confirm" required '
            . 'minlength="12" autocomplete="new-password"></label>'
            . '<button type="submit">'
            . Html::escape(
                $translator->get('auth.password_reset.submit')
            )
            . '</button></form>'
            . self::pageEnd();
    }

    public static function invalid(Translator $translator): string
    {
        return self::pageStart(
            $translator,
            $translator->get('auth.password_reset.invalid_title')
        )
            . '<h1>'
            . Html::escape(
                $translator->get('auth.password_reset.invalid_title')
            )
            . '</h1><p>'
            . Html::escape(
                $translator->get('auth.password_reset.invalid')
            )
            . '</p><p><a href="/password-reset/request/">'
            . Html::escape(
                $translator->get('auth.password_reset.request_again')
            )
            . '</a></p>'
            . self::pageEnd();
    }

    public static function complete(Translator $translator): string
    {
        return self::pageStart(
            $translator,
            $translator->get('auth.password_reset.complete_title')
        )
            . '<h1>'
            . Html::escape(
                $translator->get('auth.password_reset.complete_title')
            )
            . '</h1><p>'
            . Html::escape(
                $translator->get('auth.password_reset.complete')
            )
            . '</p><p><a href="/login/">'
            . Html::escape($translator->get('auth.login.title'))
            . '</a></p>'
            . self::pageEnd();
    }

    private static function pageStart(
        Translator $translator,
        string $title,
        string $extraHead = ''
    ): string {
        return '<!doctype html><html lang="'
            . Html::escape($translator->locale)
            . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" '
            . 'content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>' . Html::escape($title)
            . ' · Open Phonebook</title>'
            . $extraHead
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;'
            . 'max-width:520px;margin:8vh auto;padding:20px}'
            . 'label{display:block;margin-top:16px;font-weight:600}'
            . 'input{box-sizing:border-box;width:100%;'
            . 'padding:10px;margin-top:6px}'
            . 'button{margin-top:20px;padding:10px 16px;font-weight:700}'
            . '.error{padding:10px;border:1px solid currentColor;'
            . 'border-radius:6px}'
            . '</style><link rel="stylesheet" href="/assets/app.css"></head><body><main>';
    }

    private static function pageEnd(): string
    {
        return '</main></body></html>';
    }

    private function __construct()
    {
    }
}
