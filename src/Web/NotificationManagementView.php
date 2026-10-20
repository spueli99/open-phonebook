<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Devices\Device;
use OpenPhonebook\I18n\Translator;
use OpenPhonebook\Notifications\NotificationChannel;
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
        string $csrfToken,
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
            . '</style></head><body><main>'
            . '<nav><a href="/">'
            . Html::escape($translator->get('nav.dashboard'))
            . '</a><a href="/devices/">'
            . Html::escape($translator->get('nav.devices'))
            . '</a></nav>'
            . '<h1>'
            . Html::escape($translator->get('notifications.title'))
            . '</h1>'
            . $noticeHtml . $errorHtml
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
            . '</main></body></html>';
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
