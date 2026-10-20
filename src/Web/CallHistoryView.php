<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use DateTimeImmutable;
use DateTimeZone;
use OpenPhonebook\Calls\CallHistoryItem;
use OpenPhonebook\Calls\CallHistoryPage;
use OpenPhonebook\Calls\CallSessionState;
use OpenPhonebook\I18n\Translator;
use Throwable;

final class CallHistoryView
{
    public static function render(
        Translator $translator,
        CallHistoryPage $page,
        string $timezone
    ): string {
        $rows = '';

        foreach ($page->items as $item) {
            $rows .= self::row($translator, $item, $timezone);
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6">'
                . Html::escape($translator->get('calls.none'))
                . '</td></tr>';
        }

        $older = $page->olderBeforeId === null
            ? ''
            : '<p><a href="/calls/?before='
                . rawurlencode((string) $page->olderBeforeId)
                . '">'
                . Html::escape($translator->get('calls.older'))
                . '</a></p>';

        return '<!doctype html><html lang="'
            . Html::escape($translator->locale)
            . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>'
            . Html::escape($translator->get('calls.title'))
            . ' · Open Phonebook</title><style>'
            . 'body{font-family:system-ui,sans-serif;max-width:1100px;'
            . 'margin:5vh auto;padding:20px}'
            . 'table{border-collapse:collapse;width:100%}'
            . 'th,td{text-align:left;padding:8px;border-bottom:1px solid #ddd;'
            . 'vertical-align:top}'
            . '</style><link rel="stylesheet" href="/assets/app.css"></head><body>' . AppChrome::header() . '<main><p><a href="/">'
            . Html::escape($translator->get('nav.dashboard'))
            . '</a></p><h1>'
            . Html::escape($translator->get('calls.title'))
            . '</h1><p>'
            . Html::escape($translator->get('calls.help'))
            . '</p><div style="overflow-x:auto"><table><thead><tr><th>'
            . Html::escape($translator->get('calls.time'))
            . '</th><th>'
            . Html::escape($translator->get('calls.direction'))
            . '</th><th>'
            . Html::escape($translator->get('calls.status'))
            . '</th><th>'
            . Html::escape($translator->get('calls.contact'))
            . '</th><th>'
            . Html::escape($translator->get('calls.number'))
            . '</th><th>'
            . Html::escape($translator->get('calls.device'))
            . '</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table></div>'
            . $older
            . '</main>' . AppChrome::footer() . '</body></html>';
    }

    private static function row(
        Translator $translator,
        CallHistoryItem $item,
        string $timezone
    ): string {
        return '<tr><td>'
            . Html::escape(
                self::formatTime($item->startedAt, $timezone)
            )
            . '</td><td>'
            . Html::escape(
                $translator->get(
                    'calls.direction.' . $item->direction->value
                )
            )
            . '</td><td>'
            . Html::escape(self::stateLabel($translator, $item->state))
            . '</td><td>'
            . Html::escape($item->contactName ?? '—')
            . '</td><td>'
            . Html::escape($item->remoteNumber ?? '—')
            . '</td><td>'
            . Html::escape($item->deviceName)
            . '</td></tr>';
    }

    private static function stateLabel(
        Translator $translator,
        CallSessionState $state
    ): string {
        return $translator->get('calls.status.' . $state->value);
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

    private function __construct()
    {
    }
}
