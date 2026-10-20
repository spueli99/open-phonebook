<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use OpenPhonebook\Devices\Device;
use OpenPhonebook\I18n\Translator;
use OpenPhonebook\Notifications\NotificationChannel;
use OpenPhonebook\Notifications\NotificationDeliveryHistoryFilter;
use OpenPhonebook\Notifications\NotificationDeliveryHistoryPage;
use OpenPhonebook\Notifications\NotificationDeliveryStatus;
use OpenPhonebook\Notifications\NotificationRule;
use OpenPhonebook\Notifications\NotificationTrigger;

final class NotificationManagementView
{
    /**
     * @param list<NotificationChannel> $channels
     * @param list<NotificationRule> $rules
     * @param list<Device> $devices
     * @param array{
     *     pending: int,
     *     processing: int,
     *     sent: int,
     *     failed: int,
     *     cancelled: int
     * } $deliveryCounts
     */
    public static function render(
        Translator $translator,
        array $channels,
        array $rules,
        array $devices,
        array $deliveryCounts,
        NotificationDeliveryHistoryPage $deliveryHistory,
        NotificationDeliveryHistoryFilter $historyFilter,
        string $timezone,
        string $csrfToken,
        ?string $webPushPublicKey = null,
        ?string $notice = null,
        ?string $error = null
    ): string {
        $channelById = [];

        foreach ($channels as $channel) {
            $channelById[$channel->id] = $channel;
        }

        $deviceById = [];

        foreach ($devices as $device) {
            $deviceById[$device->id] = $device;
        }

        $channelHtml = '';

        foreach ($channels as $channel) {
            $stateLabel = $channel->isActive
                ? 'notifications.channel.disable'
                : 'notifications.channel.enable';
            $nextState = $channel->isActive ? '0' : '1';

            $channelHtml .= '<section class="card">'
                . '<h3>' . Html::escape($channel->name) . '</h3>'
                . '<p>'
                . Html::escape(
                    $translator->get('notifications.provider')
                )
                . ': <code>'
                . Html::escape($channel->provider)
                . '</code></p>'
                . '<p>'
                . Html::escape(
                    $translator->get(
                        $channel->isActive
                            ? 'notifications.status.active'
                            : 'notifications.status.inactive'
                    )
                )
                . '</p>'
                . self::postForm(
                    '/notifications/channel/status/',
                    $csrfToken,
                    [
                        'channel_id' => (string) $channel->id,
                        'active' => $nextState,
                    ],
                    $translator->get($stateLabel)
                )
                . (
                    $channel->isActive
                        ? self::postForm(
                            '/notifications/channel/test/',
                            $csrfToken,
                            ['channel_id' => (string) $channel->id],
                            $translator->get(
                                'notifications.channel.test'
                            )
                        )
                        : ''
                )
                . self::postForm(
                    '/notifications/channel/delete/',
                    $csrfToken,
                    ['channel_id' => (string) $channel->id],
                    $translator->get('notifications.channel.delete')
                )
                . '</section>';
        }

        if ($channelHtml === '') {
            $channelHtml = '<p>'
                . Html::escape(
                    $translator->get('notifications.channels.none')
                )
                . '</p>';
        }

        $ruleHtml = '';

        foreach ($rules as $rule) {
            $channel = $channelById[$rule->channelId] ?? null;
            $device = $rule->deviceId === null
                ? null
                : ($deviceById[$rule->deviceId] ?? null);

            if ($channel === null) {
                continue;
            }

            $ruleHtml .= '<section class="card">'
                . '<strong>'
                . Html::escape($channel->name)
                . '</strong><p>'
                . Html::escape(
                    $translator->get(
                        'notifications.trigger.' . $rule->trigger->value
                    )
                )
                . ' · '
                . Html::escape(
                    $device === null
                        ? $translator->get('notifications.device.all')
                        : $device->name
                )
                . '</p>'
                . (
                    $rule->quietStart === null
                    || $rule->quietEnd === null
                        ? ''
                        : '<p>'
                            . Html::escape(
                                $translator->get(
                                    'notifications.quiet_hours'
                                )
                            )
                            . ': '
                            . Html::escape($rule->quietStart)
                            . '–'
                            . Html::escape($rule->quietEnd)
                            . '</p>'
                )
                . self::postForm(
                    '/notifications/rule/delete/',
                    $csrfToken,
                    ['rule_id' => (string) $rule->id],
                    $translator->get('notifications.rule.delete')
                )
                . '</section>';
        }

        if ($ruleHtml === '') {
            $ruleHtml = '<p>'
                . Html::escape(
                    $translator->get('notifications.rules.none')
                )
                . '</p>';
        }

        $channelOptions = '';

        foreach ($channels as $channel) {
            $channelOptions .= '<option value="'
                . $channel->id . '">'
                . Html::escape($channel->name)
                . '</option>';
        }

        $deviceOptions = '<option value="">'
            . Html::escape(
                $translator->get('notifications.device.all')
            )
            . '</option>';

        foreach ($devices as $device) {
            $deviceOptions .= '<option value="'
                . $device->id . '">'
                . Html::escape($device->name)
                . '</option>';
        }

        $triggerOptions = '';

        foreach (NotificationTrigger::cases() as $trigger) {
            if ($trigger === NotificationTrigger::Test) {
                continue;
            }

            $triggerOptions .= '<option value="'
                . Html::escape($trigger->value)
                . '">'
                . Html::escape(
                    $translator->get(
                        'notifications.trigger.' . $trigger->value
                    )
                )
                . '</option>';
        }

        $historyHtml = self::history(
            $translator,
            $deliveryHistory,
            $historyFilter,
            $channels,
            $timezone,
            $csrfToken
        );

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

        return '<!doctype html><html lang="'
            . Html::escape($translator->locale) . '"><head>'
            . '<meta charset="utf-8"><meta name="viewport" '
            . 'content="width=device-width, initial-scale=1">'
            . '<link rel="manifest" href="/manifest.webmanifest">'
            . '<meta name="theme-color" content="#0f172a">'
            . '<script src="/assets/pwa.js" defer></script>'
            . (
                $webPushPublicKey === null
                    ? ''
                    : '<script src="/assets/web-push.js" defer></script>'
            )
            . '<title>'
            . Html::escape($translator->get('notifications.title'))
            . ' · Open Phonebook</title>'
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;max-width:1000px;'
            . 'margin:5vh auto;padding:20px}'
            . 'nav{display:flex;gap:16px;margin-bottom:24px}'
            . '.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}'
            . '.card,form.panel{border:1px solid #bbb;border-radius:8px;'
            . 'padding:16px;margin:12px 0}'
            . 'label{display:block;margin:8px 0}'
            . 'input,select,button{padding:8px}'
            . '.inline{display:inline-block;margin-right:8px}'
            . '.notice,.error{padding:10px;border:1px solid currentColor}'
            . '</style><link rel="stylesheet" href="/assets/app.css"></head><body><main>'
            . '<nav><a href="/">'
            . Html::escape($translator->get('nav.dashboard'))
            . '</a><a href="/devices/">'
            . Html::escape($translator->get('nav.devices'))
            . '</a></nav>'
            . '<h1>'
            . Html::escape($translator->get('notifications.title'))
            . '</h1>'
            . $noticeHtml . $errorHtml
            . self::webPushSetup(
                $translator,
                $webPushPublicKey,
                $csrfToken
            )
            . '<section class="card"><h2>'
            . Html::escape(
                $translator->get('notifications.delivery.title')
            )
            . '</h2><p>'
            . Html::escape(
                $translator->get('notifications.delivery.pending')
            )
            . ': ' . $deliveryCounts['pending']
            . ' · '
            . Html::escape(
                $translator->get('notifications.delivery.processing')
            )
            . ': ' . $deliveryCounts['processing']
            . ' · '
            . Html::escape(
                $translator->get('notifications.delivery.sent')
            )
            . ': ' . $deliveryCounts['sent']
            . ' · '
            . Html::escape(
                $translator->get('notifications.delivery.failed')
            )
            . ': ' . $deliveryCounts['failed']
            . ' · '
            . Html::escape(
                $translator->get('notifications.delivery.cancelled')
            )
            . ': ' . $deliveryCounts['cancelled']
            . '</p></section>'
            . '<div class="grid"><div><h2>'
            . Html::escape(
                $translator->get('notifications.channels')
            )
            . '</h2>'
            . $channelHtml
            . self::pushoverForm($translator, $csrfToken)
            . self::emailForm($translator, $csrfToken)
            . '</div><div><h2>'
            . Html::escape($translator->get('notifications.rules'))
            . '</h2>'
            . $ruleHtml
            . '<form class="panel" method="post" '
            . 'action="/notifications/rule/create/">'
            . self::csrf($csrfToken)
            . '<h3>'
            . Html::escape(
                $translator->get('notifications.rule.create')
            )
            . '</h3><label>'
            . Html::escape(
                $translator->get('notifications.channel')
            )
            . '<br><select name="channel_id" required>'
            . $channelOptions
            . '</select></label><label>'
            . Html::escape(
                $translator->get('notifications.trigger')
            )
            . '<br><select name="trigger" required>'
            . $triggerOptions
            . '</select></label><label>'
            . Html::escape(
                $translator->get('notifications.device')
            )
            . '<br><select name="device_id">'
            . $deviceOptions
            . '</select></label><label>'
            . Html::escape(
                $translator->get('notifications.quiet_start')
            )
            . '<br><input type="time" name="quiet_start"></label>'
            . '<label>'
            . Html::escape(
                $translator->get('notifications.quiet_end')
            )
            . '<br><input type="time" name="quiet_end"></label>'
            . '<button type="submit">'
            . Html::escape(
                $translator->get('notifications.rule.create')
            )
            . '</button></form></div></div>'
            . $historyHtml
            . '</main></body></html>';
    }

    private static function webPushSetup(
        Translator $translator,
        ?string $publicKey,
        string $csrfToken
    ): string {
        if ($publicKey === null) {
            return '<section class="card"><h2>Web Push</h2><p>'
                . Html::escape(
                    $translator->get(
                        'notifications.web_push.not_configured'
                    )
                )
                . '</p></section>';
        }

        return '<section class="card" id="web-push-setup" '
            . 'data-vapid-public-key="'
            . Html::escape($publicKey)
            . '" data-csrf-token="'
            . Html::escape($csrfToken)
            . '" data-label-enabled="'
            . Html::escape(
                $translator->get('notifications.web_push.enabled')
            )
            . '" data-label-disabled="'
            . Html::escape(
                $translator->get('notifications.web_push.disabled')
            )
            . '" data-label-unsupported="'
            . Html::escape(
                $translator->get('notifications.web_push.unsupported')
            )
            . '" data-label-denied="'
            . Html::escape(
                $translator->get('notifications.web_push.denied')
            )
            . '" data-label-error="'
            . Html::escape(
                $translator->get('notifications.web_push.error')
            )
            . '"><h2>Web Push</h2><p id="web-push-status">'
            . Html::escape(
                $translator->get('notifications.web_push.checking')
            )
            . '</p><button type="button" id="web-push-enable">'
            . Html::escape(
                $translator->get('notifications.web_push.enable')
            )
            . '</button> <button type="button" id="web-push-disable">'
            . Html::escape(
                $translator->get('notifications.web_push.disable')
            )
            . '</button></section>';
    }

    /**
     * @param list<NotificationDeliveryHistoryItem> $history
     */
    /**
     * @param list<NotificationChannel> $channels
     */
    private static function history(
        Translator $translator,
        NotificationDeliveryHistoryPage $page,
        NotificationDeliveryHistoryFilter $filter,
        array $channels,
        string $timezone,
        string $csrfToken
    ): string {
        $rows = '';

        foreach ($page->items as $item) {
            $channel = $item->channelName
                ?? $translator->get(
                    'notifications.history.channel_deleted'
                );
            $triggerKey = 'notifications.trigger.'
                . $item->trigger->value;
            $statusKey = 'notifications.delivery.'
                . $item->status->value;
            $retry = $item->canRetry
                ? self::postForm(
                    '/notifications/delivery/retry/',
                    $csrfToken,
                    ['delivery_id' => (string) $item->id],
                    $translator->get(
                        'notifications.delivery.retry'
                    )
                )
                : '';

            $rows .= '<tr><td>'
                . Html::escape($channel)
                . '</td><td>'
                . Html::escape($translator->get($triggerKey))
                . '</td><td>'
                . Html::escape($translator->get($statusKey))
                . '</td><td>'
                . $item->attempts
                . '</td><td>'
                . Html::escape(
                    self::formatTime(
                        $item->createdAt,
                        $timezone
                    )
                )
                . '</td><td>'
                . Html::escape(
                    self::formatTime(
                        $item->availableAt,
                        $timezone
                    )
                )
                . '</td><td>'
                . (
                    $item->lastErrorAt === null
                        ? '—'
                        : Html::escape(
                            self::formatTime(
                                $item->lastErrorAt,
                                $timezone
                            )
                        )
                )
                . '</td><td>'
                . (
                    $item->sentAt === null
                        ? '—'
                        : Html::escape(
                            self::formatTime(
                                $item->sentAt,
                                $timezone
                            )
                        )
                )
                . '</td><td>'
                . $retry
                . '</td></tr>';
        }

        $table = $rows === ''
            ? '<p>'
                . Html::escape(
                    $translator->get(
                        'notifications.history.none_filtered'
                    )
                )
                . '</p>'
            : '<div style="overflow-x:auto"><table>'
                . '<thead><tr><th>'
                . Html::escape(
                    $translator->get('notifications.channel')
                )
                . '</th><th>'
                . Html::escape(
                    $translator->get('notifications.trigger')
                )
                . '</th><th>'
                . Html::escape(
                    $translator->get('devices.status')
                )
                . '</th><th>'
                . Html::escape(
                    $translator->get(
                        'notifications.history.attempts'
                    )
                )
                . '</th><th>'
                . Html::escape(
                    $translator->get(
                        'notifications.history.created'
                    )
                )
                . '</th><th>'
                . Html::escape(
                    $translator->get(
                        'notifications.history.available'
                    )
                )
                . '</th><th>'
                . Html::escape(
                    $translator->get(
                        'notifications.history.last_error'
                    )
                )
                . '</th><th>'
                . Html::escape(
                    $translator->get(
                        'notifications.history.sent'
                    )
                )
                . '</th><th>'
                . Html::escape(
                    $translator->get('users.actions')
                )
                . '</th></tr></thead><tbody>'
                . $rows
                . '</tbody></table></div>';

        $older = '';

        if ($page->olderBeforeId !== null) {
            $query = self::historyQuery($filter);
            $query['before'] = (string) $page->olderBeforeId;
            $older = '<p><a href="/notifications/?'
                . Html::escape(http_build_query($query))
                . '">'
                . Html::escape(
                    $translator->get('notifications.history.older')
                )
                . '</a></p>';
        }

        return '<section class="card"><h2>'
            . Html::escape(
                $translator->get('notifications.history.title')
            )
            . '</h2>'
            . self::historyFilterForm(
                $translator,
                $filter,
                $channels
            )
            . $table
            . $older
            . '</section>';
    }

    /**
     * @param list<NotificationChannel> $channels
     */
    private static function historyFilterForm(
        Translator $translator,
        NotificationDeliveryHistoryFilter $filter,
        array $channels
    ): string {
        $statusOptions = '<option value="">'
            . Html::escape(
                $translator->get('notifications.history.all_statuses')
            )
            . '</option>';

        foreach (NotificationDeliveryStatus::cases() as $status) {
            $selected = $filter->status === $status
                ? ' selected'
                : '';
            $statusOptions .= '<option value="'
                . Html::escape($status->value)
                . '"' . $selected . '>'
                . Html::escape(
                    $translator->get(
                        'notifications.delivery.' . $status->value
                    )
                )
                . '</option>';
        }

        $triggerOptions = '<option value="">'
            . Html::escape(
                $translator->get('notifications.history.all_triggers')
            )
            . '</option>';

        foreach (NotificationTrigger::cases() as $trigger) {
            $selected = $filter->trigger === $trigger
                ? ' selected'
                : '';
            $triggerOptions .= '<option value="'
                . Html::escape($trigger->value)
                . '"' . $selected . '>'
                . Html::escape(
                    $translator->get(
                        'notifications.trigger.' . $trigger->value
                    )
                )
                . '</option>';
        }

        $channelOptions = '<option value="">'
            . Html::escape(
                $translator->get('notifications.history.all_channels')
            )
            . '</option>';

        foreach ($channels as $channel) {
            $selected = $filter->channelId === $channel->id
                ? ' selected'
                : '';
            $channelOptions .= '<option value="'
                . $channel->id . '"' . $selected . '>'
                . Html::escape($channel->name)
                . '</option>';
        }

        return '<form method="get" action="/notifications/">'
            . '<label>'
            . Html::escape($translator->get('devices.status'))
            . ' <select name="status">'
            . $statusOptions
            . '</select></label> '
            . '<label>'
            . Html::escape($translator->get('notifications.trigger'))
            . ' <select name="trigger">'
            . $triggerOptions
            . '</select></label> '
            . '<label>'
            . Html::escape($translator->get('notifications.channel'))
            . ' <select name="channel">'
            . $channelOptions
            . '</select></label> '
            . '<button type="submit">'
            . Html::escape(
                $translator->get('notifications.history.filter')
            )
            . '</button> '
            . '<a href="/notifications/">'
            . Html::escape(
                $translator->get('notifications.history.reset')
            )
            . '</a></form>';
    }

    /**
     * @return array<string, string>
     */
    private static function historyQuery(
        NotificationDeliveryHistoryFilter $filter
    ): array {
        $query = [];

        if ($filter->status !== null) {
            $query['status'] = $filter->status->value;
        }

        if ($filter->trigger !== null) {
            $query['trigger'] = $filter->trigger->value;
        }

        if ($filter->channelId !== null) {
            $query['channel'] = (string) $filter->channelId;
        }

        return $query;
    }

    private static function formatTime(
        DateTimeImmutable $time,
        string $timezone
    ): string {
        try {
            $zone = new DateTimeZone($timezone);
        } catch (Throwable) {
            $zone = new DateTimeZone('UTC');
        }

        return $time
            ->setTimezone($zone)
            ->format('Y-m-d H:i:s T');
    }

    private static function pushoverForm(
        Translator $translator,
        string $csrfToken
    ): string {
        return '<form class="panel" method="post" '
            . 'action="/notifications/channel/create/">'
            . self::csrf($csrfToken)
            . '<input type="hidden" name="provider" value="pushover">'
            . '<h3>Pushover</h3>'
            . self::textInput(
                $translator->get('notifications.channel.name'),
                'name',
                120
            )
            . self::textInput(
                'Application API token',
                'token',
                30,
                'password'
            )
            . self::textInput(
                'User/group key',
                'user',
                30,
                'password'
            )
            . '<button type="submit">'
            . Html::escape(
                $translator->get('notifications.channel.create')
            )
            . '</button></form>';
    }

    private static function emailForm(
        Translator $translator,
        string $csrfToken
    ): string {
        return '<form class="panel" method="post" '
            . 'action="/notifications/channel/create/">'
            . self::csrf($csrfToken)
            . '<input type="hidden" name="provider" value="email">'
            . '<h3>E-Mail</h3>'
            . self::textInput(
                $translator->get('notifications.channel.name'),
                'name',
                120
            )
            . self::textInput(
                $translator->get('notifications.email.recipient'),
                'recipient',
                254,
                'email'
            )
            . self::textInput(
                $translator->get('notifications.email.from'),
                'from',
                254,
                'email'
            )
            . '<button type="submit">'
            . Html::escape(
                $translator->get('notifications.channel.create')
            )
            . '</button></form>';
    }

    private static function textInput(
        string $label,
        string $name,
        int $maxLength,
        string $type = 'text'
    ): string {
        return '<label>' . Html::escape($label)
            . '<br><input type="' . Html::escape($type)
            . '" name="' . Html::escape($name)
            . '" maxlength="' . $maxLength
            . '" required autocomplete="off"></label>';
    }

    /**
     * @param array<string, string> $fields
     */
    private static function postForm(
        string $action,
        string $csrfToken,
        array $fields,
        string $label
    ): string {
        $html = '<form class="inline" method="post" action="'
            . Html::escape($action) . '">'
            . self::csrf($csrfToken);

        foreach ($fields as $name => $value) {
            $html .= '<input type="hidden" name="'
                . Html::escape($name) . '" value="'
                . Html::escape($value) . '">';
        }

        return $html . '<button type="submit">'
            . Html::escape($label)
            . '</button></form>';
    }

    private static function csrf(string $csrfToken): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . Html::escape($csrfToken) . '">';
    }

    private function __construct()
    {
    }
}
