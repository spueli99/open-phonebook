<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Auth\AuthenticatedUser;
use OpenPhonebook\I18n\Translator;

final class DashboardView
{
    public static function render(
        Translator $translator,
        AuthenticatedUser $user,
        string $csrfToken
    ): string {
        $name = htmlspecialchars(
            $user->displayName,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return '<!doctype html>'
            . '<html lang="' . htmlspecialchars($translator->locale, ENT_QUOTES, 'UTF-8') . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . htmlspecialchars(
                $translator->get('dashboard.title'),
                ENT_QUOTES,
                'UTF-8'
            )
            . ' · Open Phonebook</title></head><body><main>'
            . '<h1>' . htmlspecialchars(
                $translator->get('dashboard.title'),
                ENT_QUOTES,
                'UTF-8'
            ) . '</h1>'
            . '<p>' . htmlspecialchars(
                $translator->get('dashboard.welcome'),
                ENT_QUOTES,
                'UTF-8'
            ) . ' ' . $name . '</p>'
            . '<form method="post" action="/logout/">'
            . '<input type="hidden" name="_csrf" value="' . htmlspecialchars(
                $csrfToken,
                ENT_QUOTES,
                'UTF-8'
            ) . '">'
            . '<button type="submit">' . htmlspecialchars(
                $translator->get('auth.logout'),
                ENT_QUOTES,
                'UTF-8'
            ) . '</button></form>'
            . '</main></body></html>';
    }

    private function __construct()
    {
    }
}
