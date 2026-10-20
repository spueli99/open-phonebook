<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use DateTimeImmutable;
use DateTimeZone;
use OpenPhonebook\Contacts\AddressBookAccess;
use OpenPhonebook\Devices\Device;
use OpenPhonebook\Devices\DeviceCredentialCreationResult;
use OpenPhonebook\I18n\Translator;
use Throwable;

final class DeviceManagementView
{
    /**
     * @param list<Device> $devices
     * @param list<AddressBookAccess> $addressBooks
     * @param array<int, list<int>> $assignments
     * @param array<int, bool> $phonebookCredentialStates
     * @param array<int, bool> $callEventCredentialStates
     * @param array<int, bool> $yealinkCallbackCredentialStates
     * @param array<int, ?DateTimeImmutable> $phonebookLastUsedAt
     * @param array<int, ?DateTimeImmutable> $callEventLastUsedAt
     * @param array<int, ?DateTimeImmutable> $yealinkCallbackLastUsedAt
     */
    public static function render(
        Translator $translator,
        array $devices,
        array $addressBooks,
        array $assignments,
        array $phonebookCredentialStates,
        array $callEventCredentialStates,
        array $yealinkCallbackCredentialStates,
        array $phonebookLastUsedAt,
        array $callEventLastUsedAt,
        array $yealinkCallbackLastUsedAt,
        string $timezone,
        string $csrfToken,
        ?DeviceCredentialCreationResult $credential = null,
        ?string $notice = null,
        ?string $error = null
    ): string {
        $credentialHtml = '';

        if ($credential !== null) {
            $credentialHtml = '<section class="secret" role="status">'
                . '<h2>'
                . Html::escape(
                    $translator->get('devices.credential')
                )
                . '</h2>'
                . '<p>'
                . Html::escape(
                    $translator->get('devices.credential.once')
                )
                . '</p>'
                . '<p><strong>'
                . Html::escape($credential->device->name)
                . '</strong></p>'
                . '<p>'
                . Html::escape(
                    $translator->get('devices.credential.scope')
                )
                . ': <code>'
                . Html::escape($credential->scope->value)
                . '</code></p>'
                . '<pre>'
                . Html::escape($credential->plainTextToken)
                . '</pre></section>';
        }

        $noticeHtml = $notice === null
            ? ''
            : '<p class="notice" role="status">'
                . Html::escape($notice)
                . '</p>';
        $errorHtml = $error === null
            ? ''
            : '<p class="error" role="alert">'
                . Html::escape($error)
                . '</p>';

        $deviceHtml = '';

        foreach ($devices as $device) {
            $deviceAssignments = $assignments[$device->id] ?? [];
            $hasPhonebookCredential =
                $phonebookCredentialStates[$device->id] ?? false;
            $hasCallEventCredential =
                $callEventCredentialStates[$device->id] ?? false;
            $hasYealinkCallbackCredential =
                $yealinkCallbackCredentialStates[$device->id] ?? false;
            $statusKey = $device->isActive
                ? 'devices.status.active'
                : 'devices.status.inactive';
            $phonebookCredentialKey = $hasPhonebookCredential
                ? 'devices.credential.active'
                : 'devices.credential.none';
            $callEventCredentialKey = $hasCallEventCredential
                ? 'devices.credential.active'
                : 'devices.credential.none';
            $yealinkCallbackCredentialKey = $hasYealinkCallbackCredential
                ? 'devices.credential.active'
                : 'devices.credential.none';
            $phonebookLastUsed = self::lastUsedLabel(
                $translator,
                $phonebookLastUsedAt[$device->id] ?? null,
                $hasPhonebookCredential,
                $timezone
            );
            $callEventLastUsed = self::lastUsedLabel(
                $translator,
                $callEventLastUsedAt[$device->id] ?? null,
                $hasCallEventCredential,
                $timezone
            );
            $yealinkCallbackLastUsed = self::lastUsedLabel(
                $translator,
                $yealinkCallbackLastUsedAt[$device->id] ?? null,
                $hasYealinkCallbackCredential,
                $timezone
            );

            $bookOptions = '';

            foreach ($addressBooks as $access) {
                $addressBook = $access->addressBook;
                $checked = in_array(
                    $addressBook->id,
                    $deviceAssignments,
                    true
                )
                    ? ' checked'
                    : '';

                $bookOptions .= '<label class="book">'
                    . '<input type="checkbox" '
                    . 'name="address_book_ids[]" value="'
                    . $addressBook->id . '"'
                    . $checked . '> '
                    . Html::escape($addressBook->name)
                    . '</label>';
            }

            if ($bookOptions === '') {
                $bookOptions = '<p>—</p>';
            }

            $stateAction = $device->isActive
                ? '0'
                : '1';
            $stateLabel = $device->isActive
                ? 'devices.action.deactivate'
                : 'devices.action.activate';

            $deviceHtml .= '<section class="device">'
                . '<h2>' . Html::escape($device->name) . '</h2>'
                . '<dl>'
                . '<dt>'
                . Html::escape($translator->get('devices.vendor'))
                . '</dt><dd>'
                . Html::escape($device->vendor ?? '—')
                . '</dd>'
                . '<dt>'
                . Html::escape($translator->get('devices.model'))
                . '</dt><dd>'
                . Html::escape($device->model ?? '—')
                . '</dd>'
                . '<dt>'
                . Html::escape($translator->get('devices.status'))
                . '</dt><dd>'
                . Html::escape($translator->get($statusKey))
                . '</dd>'
                . '<dt>'
                . Html::escape(
                    $translator->get('devices.credential.phonebook')
                )
                . '</dt><dd>'
                . Html::escape(
                    $translator->get($phonebookCredentialKey)
                )
                . '<br><small>'
                . Html::escape($phonebookLastUsed)
                . '</small></dd>'
                . '<dt>'
                . Html::escape(
                    $translator->get('devices.credential.call_events')
                )
                . '</dt><dd>'
                . Html::escape(
                    $translator->get($callEventCredentialKey)
                )
                . '<br><small>'
                . Html::escape($callEventLastUsed)
                . '</small></dd>'
                . '<dt>'
                . Html::escape(
                    $translator->get('devices.credential.yealink_callback')
                )
                . '</dt><dd>'
                . Html::escape(
                    $translator->get($yealinkCallbackCredentialKey)
                )
                . '<br><small>'
                . Html::escape($yealinkCallbackLastUsed)
                . '</small></dd></dl>'
                . '<form method="post" action="/devices/update/" '
                . 'class="identity">'
                . '<input type="hidden" name="_csrf" value="'
                . Html::escape($csrfToken) . '">'
                . '<input type="hidden" name="device_id" value="'
                . $device->id . '">'
                . '<label>'
                . Html::escape($translator->get('devices.name'))
                . '<br><input name="name" maxlength="120" required value="'
                . Html::escape($device->name) . '"></label>'
                . '<label>'
                . Html::escape($translator->get('devices.vendor'))
                . '<br><input name="vendor" maxlength="120" value="'
                . Html::escape($device->vendor ?? '') . '"></label>'
                . '<label>'
                . Html::escape($translator->get('devices.model'))
                . '<br><input name="model" maxlength="120" value="'
                . Html::escape($device->model ?? '') . '"></label>'
                . '<button type="submit">'
                . Html::escape($translator->get('devices.save'))
                . '</button></form>'
                . '<div class="actions">'
                . self::actionForm(
                    '/devices/status/',
                    $device->id,
                    $csrfToken,
                    $translator->get($stateLabel),
                    '<input type="hidden" name="active" value="'
                        . $stateAction . '">'
                )
                . self::actionForm(
                    '/devices/credential/rotate/',
                    $device->id,
                    $csrfToken,
                    $translator->get(
                        'devices.credential.rotate'
                    )
                )
                . self::actionForm(
                    '/devices/credential/revoke/',
                    $device->id,
                    $csrfToken,
                    $translator->get(
                        'devices.credential.revoke'
                    )
                )
                . self::actionForm(
                    '/devices/call-events/credential/rotate/',
                    $device->id,
                    $csrfToken,
                    $translator->get(
                        'devices.call_events.rotate'
                    )
                )
                . self::actionForm(
                    '/devices/call-events/credential/revoke/',
                    $device->id,
                    $csrfToken,
                    $translator->get(
                        'devices.call_events.revoke'
                    )
                )
                . self::actionForm(
                    '/devices/yealink-callback/credential/rotate/',
                    $device->id,
                    $csrfToken,
                    $translator->get(
                        'devices.yealink_callback.rotate'
                    )
                )
                . self::actionForm(
                    '/devices/yealink-callback/credential/revoke/',
                    $device->id,
                    $csrfToken,
                    $translator->get(
                        'devices.yealink_callback.revoke'
                    )
                )
                . '</div>'
                . '<form method="post" '
                . 'action="/devices/address-books/" class="books">'
                . '<input type="hidden" name="_csrf" value="'
                . Html::escape($csrfToken) . '">'
                . '<input type="hidden" name="device_id" value="'
                . $device->id . '">'
                . '<fieldset><legend>'
                . Html::escape(
                    $translator->get('devices.address_books')
                )
                . '</legend>'
                . $bookOptions
                . '</fieldset>'
                . '<button type="submit">'
                . Html::escape(
                    $translator->get('devices.address_books.save')
                )
                . '</button></form>'
                . '</section>';
        }

        if ($deviceHtml === '') {
            $deviceHtml = '<p>'
                . Html::escape($translator->get('devices.none'))
                . '</p>';
        }

        return '<!doctype html><html lang="'
            . Html::escape($translator->locale) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" '
            . 'content="width=device-width, initial-scale=1">'
            . '<title>'
            . Html::escape($translator->get('devices.title'))
            . ' · Open Phonebook</title>'
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;max-width:1000px;'
            . 'margin:5vh auto;padding:20px}'
            . 'nav{display:flex;gap:16px;margin-bottom:24px}'
            . 'form.create{display:grid;grid-template-columns:1fr 1fr 1fr auto;'
            . 'gap:8px;align-items:end;margin-bottom:28px}'
            . '.device,.secret{border:1px solid #bbb;border-radius:8px;'
            . 'padding:16px;margin:18px 0}'
            . '.secret pre{overflow:auto;padding:10px;border:1px solid #bbb}'
            . 'dl{display:grid;grid-template-columns:160px 1fr;gap:6px}'
            . 'dt{font-weight:700}.actions{display:flex;gap:8px;flex-wrap:wrap}'
            . '.actions form{margin:0}.books{margin-top:16px}'
            . '.identity{display:grid;grid-template-columns:1fr 1fr 1fr auto;'
            . 'gap:8px;align-items:end;margin:16px 0}'
            . '.book{display:block;margin:6px 0}'
            . '.notice,.error{padding:10px;border:1px solid currentColor}'
            . 'input,button{padding:8px}'
            . '</style><link rel="stylesheet" href="/assets/app.css"></head><body><main>'
            . '<nav><a href="/">'
            . Html::escape($translator->get('nav.dashboard'))
            . '</a><a href="/address-books/">'
            . Html::escape($translator->get('nav.contacts'))
            . '</a></nav>'
            . '<h1>'
            . Html::escape($translator->get('devices.title'))
            . '</h1>'
            . $credentialHtml
            . $noticeHtml
            . $errorHtml
            . '<form method="post" action="/devices/create/" '
            . 'class="create">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken) . '">'
            . '<label>'
            . Html::escape($translator->get('devices.name'))
            . '<br><input name="name" maxlength="120" required></label>'
            . '<label>'
            . Html::escape($translator->get('devices.vendor'))
            . '<br><input name="vendor" maxlength="120"></label>'
            . '<label>'
            . Html::escape($translator->get('devices.model'))
            . '<br><input name="model" maxlength="120"></label>'
            . '<button type="submit">'
            . Html::escape($translator->get('devices.create'))
            . '</button></form>'
            . $deviceHtml
            . '</main></body></html>';
    }

    private static function lastUsedLabel(
        Translator $translator,
        ?DateTimeImmutable $lastUsedAt,
        bool $hasActiveCredential,
        string $timezone
    ): string {
        if (!$hasActiveCredential) {
            return $translator->get('devices.credential.last_used.none');
        }

        if ($lastUsedAt === null) {
            return $translator->get('devices.credential.last_used.never');
        }

        try {
            $zone = new DateTimeZone($timezone);
        } catch (Throwable) {
            $zone = new DateTimeZone('UTC');
        }

        return $translator->get('devices.credential.last_used')
            . ': '
            . $lastUsedAt
                ->setTimezone($zone)
                ->format('Y-m-d H:i:s T');
    }

    private static function actionForm(
        string $action,
        int $deviceId,
        string $csrfToken,
        string $label,
        string $extraFields = ''
    ): string {
        return '<form method="post" action="'
            . Html::escape($action) . '">'
            . '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken) . '">'
            . '<input type="hidden" name="device_id" value="'
            . $deviceId . '">'
            . $extraFields
            . '<button type="submit">'
            . Html::escape($label)
            . '</button></form>';
    }

    private function __construct()
    {
    }
}
