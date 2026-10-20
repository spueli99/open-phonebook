<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Auth\AuthenticatedUser;
use OpenPhonebook\I18n\Translator;
use OpenPhonebook\UserManagement\Invitation;
use OpenPhonebook\UserManagement\UserSummary;

final class UserManagementView
{
    /**
     * @param list<UserSummary> $users
     * @param list<Invitation> $invitations
     */
    public static function render(
        Translator $translator,
        AuthenticatedUser $currentUser,
        array $users,
        array $invitations,
        string $csrfToken,
        ?string $notice = null,
        ?string $error = null
    ): string {
        $userRows = '';

        foreach ($users as $user) {
            $statusKey = $user->isDeleted
                ? 'users.status.deleted'
                : ($user->isActive ? 'users.status.active' : 'users.status.inactive');

            $actions = '';

            if (!$user->isDeleted && $user->id !== $currentUser->id) {
                $desiredState = $user->isActive ? '0' : '1';
                $labelKey = $user->isActive
                    ? 'users.action.deactivate'
                    : 'users.action.activate';

                $actions .= '<form method="post" action="/admin/users/status/">'
                    . '<input type="hidden" name="_csrf" value="'
                    . Html::escape($csrfToken) . '">'
                    . '<input type="hidden" name="user_id" value="'
                    . $user->id . '">'
                    . '<input type="hidden" name="active" value="'
                    . $desiredState . '">'
                    . '<button type="submit">'
                    . Html::escape($translator->get($labelKey))
                    . '</button></form>';

                $actions .= '<form method="post" action="/admin/users/role/">'
                    . '<input type="hidden" name="_csrf" value="'
                    . Html::escape($csrfToken) . '">'
                    . '<input type="hidden" name="user_id" value="'
                    . $user->id . '">'
                    . '<select name="role" aria-label="'
                    . Html::escape($translator->get('users.role')) . '">'
                    . self::roleOption($translator, 'member', $user->role)
                    . self::roleOption($translator, 'system_admin', $user->role)
                    . '</select>'
                    . '<button type="submit">'
                    . Html::escape($translator->get('users.action.change_role'))
                    . '</button></form>';
            }

            $roleKey = 'role.' . $user->role;
            $roleLabel = in_array($user->role, ['member', 'system_admin'], true)
                ? $translator->get($roleKey)
                : $user->role;

            $userRows .= '<tr>'
                . '<td>' . Html::escape($user->displayName) . '</td>'
                . '<td>' . Html::escape($user->email) . '</td>'
                . '<td>' . Html::escape($roleLabel) . '</td>'
                . '<td>' . Html::escape($translator->get($statusKey)) . '</td>'
                . '<td class="actions">' . $actions . '</td>'
                . '</tr>';
        }

        $invitationRows = '';

        foreach ($invitations as $invitation) {
            $roleKey = 'role.' . $invitation->role;
            $roleLabel = in_array($invitation->role, ['member', 'system_admin'], true)
                ? $translator->get($roleKey)
                : $invitation->role;

            $invitationRows .= '<tr>'
                . '<td>' . Html::escape($invitation->email) . '</td>'
                . '<td>' . Html::escape($roleLabel) . '</td>'
                . '<td>' . Html::escape($invitation->expiresAt->format('Y-m-d H:i')) . ' UTC</td>'
                . '<td class="actions">'
                . self::invitationAction(
                    '/admin/users/invitations/renew/',
                    $invitation->id,
                    $csrfToken,
                    $translator->get('users.invitation.renew')
                )
                . self::invitationAction(
                    '/admin/users/invitations/revoke/',
                    $invitation->id,
                    $csrfToken,
                    $translator->get('users.invitation.revoke')
                )
                . '</td></tr>';
        }

        if ($invitationRows === '') {
            $invitationRows = '<tr><td colspan="4">'
                . Html::escape($translator->get('users.invitation.none'))
                . '</td></tr>';
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
            . 'body{font-family:system-ui,sans-serif;max-width:1100px;margin:5vh auto;padding:20px}'
            . 'table{width:100%;border-collapse:collapse;margin:18px 0 32px}'
            . 'th,td{text-align:left;padding:10px;border-bottom:1px solid #bbb;vertical-align:top}'
            . 'nav{display:flex;gap:16px;margin-bottom:24px}'
            . '.notice,.error{padding:10px;border:1px solid currentColor;border-radius:6px}'
            . '.actions{display:flex;gap:8px;flex-wrap:wrap}'
            . '.actions form{display:flex;gap:6px;margin:0}'
            . 'button,a.button,select{padding:8px 12px}'
            . '</style><link rel="stylesheet" href="/assets/app.css"></head><body><main>'
            . '<nav><a href="/">' . Html::escape($translator->get('nav.dashboard'))
            . '</a><a href="/admin/users/invite/">'
            . Html::escape($translator->get('users.invite.title')) . '</a></nav>'
            . '<h1>' . Html::escape($translator->get('users.title')) . '</h1>'
            . $noticeHtml
            . $errorHtml
            . '<h2>' . Html::escape($translator->get('users.accounts')) . '</h2>'
            . '<table><thead><tr>'
            . '<th>' . Html::escape($translator->get('users.name')) . '</th>'
            . '<th>' . Html::escape($translator->get('users.email')) . '</th>'
            . '<th>' . Html::escape($translator->get('users.role')) . '</th>'
            . '<th>' . Html::escape($translator->get('users.status')) . '</th>'
            . '<th>' . Html::escape($translator->get('users.actions')) . '</th>'
            . '</tr></thead><tbody>' . $userRows . '</tbody></table>'
            . '<h2>' . Html::escape($translator->get('users.invitations')) . '</h2>'
            . '<table><thead><tr>'
            . '<th>' . Html::escape($translator->get('users.email')) . '</th>'
            . '<th>' . Html::escape($translator->get('users.role')) . '</th>'
            . '<th>' . Html::escape($translator->get('users.invitation.expires')) . '</th>'
            . '<th>' . Html::escape($translator->get('users.actions')) . '</th>'
            . '</tr></thead><tbody>' . $invitationRows . '</tbody></table>'
            . '</main></body></html>';
    }

    private static function roleOption(
        Translator $translator,
        string $role,
        string $currentRole
    ): string {
        return '<option value="' . Html::escape($role) . '"'
            . ($role === $currentRole ? ' selected' : '')
            . '>' . Html::escape($translator->get('role.' . $role)) . '</option>';
    }

    private static function invitationAction(
        string $action,
        int $invitationId,
        string $csrfToken,
        string $label
    ): string {
        return '<form method="post" action="' . Html::escape($action) . '">'
            . '<input type="hidden" name="_csrf" value="' . Html::escape($csrfToken) . '">'
            . '<input type="hidden" name="invitation_id" value="' . $invitationId . '">'
            . '<button type="submit">' . Html::escape($label) . '</button></form>';
    }

    private function __construct()
    {
    }
}
