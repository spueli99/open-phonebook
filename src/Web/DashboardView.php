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
        string $csrfToken,
        bool $canManageUsers,
        bool $canViewOperations,
        bool $canViewAudit
    ): string {
        $name = Html::escape($user->displayName);
        $links = [
            self::card('/address-books/', $translator->get('nav.contacts')),
            self::card('/devices/', $translator->get('nav.devices')),
            self::card(
                '/notifications/',
                $translator->get('nav.notifications')
            ),
            self::card('/calls/', $translator->get('nav.calls')),
        ];

        if ($canViewOperations) {
            $links[] = self::card(
                '/admin/operations/',
                $translator->get('nav.operations')
            );
        }

        if ($canViewAudit) {
            $links[] = self::card(
                '/admin/audit/',
                $translator->get('nav.audit')
            );
        }

        if ($canManageUsers) {
            $links[] = self::card(
                '/admin/users/',
                $translator->get('nav.users')
            );
        }

        return '<!doctype html>'
            . '<html lang="' . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<link rel="manifest" href="/manifest.webmanifest">'
            . '<meta name="theme-color" content="#0f172a">'
            . '<script src="/assets/pwa.js" defer></script>'
            . '<title>' . Html::escape($translator->get('dashboard.title'))
            . ' · Open Phonebook</title>'
            . '<link rel="stylesheet" href="/assets/app.css">'
            . '</head><body><main class="dashboard-shell">'
            . '<header class="dashboard-header">'
            . '<div><h1>'
            . Html::escape($translator->get('dashboard.title'))
            . '</h1><p>'
            . Html::escape($translator->get('dashboard.welcome'))
            . ' ' . $name
            . '</p></div>'
            . '<form method="post" action="/logout/" class="dashboard-logout">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken) . '">'
            . '<button type="submit">'
            . Html::escape($translator->get('auth.logout'))
            . '</button></form></header>'
            . '<nav class="dashboard-grid" aria-label="'
            . Html::escape($translator->get('dashboard.title'))
            . '">'
            . implode('', $links)
            . '</nav>'
            . '</main></body></html>';
    }

    private static function card(string $href, string $label): string
    {
        return '<a class="dashboard-card" href="'
            . Html::escape($href)
            . '"><span>'
            . Html::escape($label)
            . '</span><span aria-hidden="true">→</span></a>';
    }

    private function __construct()
    {
    }
}
