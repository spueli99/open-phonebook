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
        $adminLink = $canManageUsers
            ? '<p><a href="/admin/users/">'
                . Html::escape($translator->get('nav.users'))
                . '</a></p>'
            : '';
        $contactsLink = '<p><a href="/address-books/">'
            . Html::escape($translator->get('nav.contacts'))
            . '</a></p>';
        $devicesLink = '<p><a href="/devices/">'
            . Html::escape($translator->get('nav.devices'))
            . '</a></p>';
        $notificationsLink = '<p><a href="/notifications/">'
            . Html::escape($translator->get('nav.notifications'))
            . '</a></p>';
        $callsLink = '<p><a href="/calls/">'
            . Html::escape($translator->get('nav.calls'))
            . '</a></p>';
        $operationsLink = $canViewOperations
            ? '<p><a href="/admin/operations/">'
                . Html::escape($translator->get('nav.operations'))
                . '</a></p>'
            : '';
        $auditLink = $canViewAudit
            ? '<p><a href="/admin/audit/">'
                . Html::escape($translator->get('nav.audit'))
                . '</a></p>'
            : '';

        return '<!doctype html>'
            . '<html lang="' . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<link rel="manifest" href="/manifest.webmanifest">'
            . '<meta name="theme-color" content="#0f172a">'
            . '<script src="/assets/pwa.js" defer></script>'
            . '<title>' . Html::escape($translator->get('dashboard.title'))
            . ' · Open Phonebook</title><link rel="stylesheet" href="/assets/app.css"></head><body><main>'
            . '<h1>' . Html::escape($translator->get('dashboard.title')) . '</h1>'
            . '<p>' . Html::escape($translator->get('dashboard.welcome'))
            . ' ' . $name . '</p>'
            . $contactsLink
            . $devicesLink
            . $notificationsLink
            . $callsLink
            . $operationsLink
            . $auditLink
            . $adminLink
            . '<form method="post" action="/logout/">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken) . '">'
            . '<button type="submit">'
            . Html::escape($translator->get('auth.logout'))
            . '</button></form>'
            . '</main></body></html>';
    }

    private function __construct()
    {
    }
}
