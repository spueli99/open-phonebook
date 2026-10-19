<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Auth\AuthenticatedUser;
use OpenPhonebook\I18n\Translator;
use OpenPhonebook\UserManagement\UserSummary;

final class UserManagementView
{
    /**
     * @param list<UserSummary> $users
     */
    public static function render(
        Translator $translator,
        AuthenticatedUser $currentUser,
        array $users,
        string $csrfToken,
        ?string $notice = null,
        ?string $error = null
    ): string {
        $rows = '';

        foreach ($users as $user) {
            $statusKey = $user->isDeleted
                ? 'users.status.deleted'
                : ($user->isActive ? 'users.status.active' : 'users.status.inactive');

            $action = '';

            if (!$user->isDeleted && $user->id !== $currentUser->id) {
                $desiredState = $user->isActive ? '0' : '1';
                $labelKey = $user->isActive
                    ? 'users.action.deactivate'
                    : 'users.action.activate';

                $action = '<form method="post" action="/admin/users/status/">'
                    . '<input type="hidden" name="_csrf" value="'
                    . Html::escape($csrfToken) . '">'
                    . '<input type="hidden" name="user_id" value="'
                    . $user->id . '">'
                    . '<input type="hidden" name="active" value="'
                    . $desiredState . '">'
                    . '<button type="submit">'
                    . Html::escape($translator->get($labelKey))
                    . '</button></form>';
            }

            $rows .= '<tr>'
                . '<td>' . Html::escape($user->displayName) . '</td>'
                . '<td>' . Html::escape($user->email) . '</td>'
                . '<td>' . Html::escape($user->role) . '</td>'
                . '<td>' . Html::escape($translator->get($statusKey)) . '</td>'
                . '<td>' . $action . '</td>'
                . '</tr>';
        }

        $noticeHtml = $notice === null
            ? ''
            : '<p class="notice" role="status">' . Html::escape($notice) . '</p>';

        $errorHtml = $error === null
            ? ''
            : '<p class="error" role="alert">' . Html::escape($error) . '</p>';

        return '<!doctype html>'
            . '<html lang="' . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . Html::escape($translator->get('users.title'))
            . ' · Open Phonebook</title>'
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;max-width:1050px;margin:5vh auto;padding:20px}'
            . 'table{width:100%;border-collapse:collapse;margin-top:24px}'
            . 'th,td{text-align:left;padding:10px;border-bottom:1px solid #bbb}'
            . 'nav{display:flex;gap:16px;margin-bottom:24px}'
            . '.notice,.error{padding:10px;border:1px solid currentColor;border-radius:6px}'
            . 'button,a.button{padding:8px 12px}'
            . '</style></head><body><main>'
            . '<nav><a href="/">' . Html::escape($translator->get('nav.dashboard'))
            . '</a><a href="/admin/users/invite/">'
            . Html::escape($translator->get('users.invite.title')) . '</a></nav>'
            . '<h1>' . Html::escape($translator->get('users.title')) . '</h1>'
            . $noticeHtml
            . $errorHtml
            . '<table><thead><tr>'
            . '<th>' . Html::escape($translator->get('users.name')) . '</th>'
            . '<th>' . Html::escape($translator->get('users.email')) . '</th>'
            . '<th>' . Html::escape($translator->get('users.role')) . '</th>'
            . '<th>' . Html::escape($translator->get('users.status')) . '</th>'
            . '<th>' . Html::escape($translator->get('users.actions')) . '</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '</main></body></html>';
    }

    private function __construct()
    {
    }
}
