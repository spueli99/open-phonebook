<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use DateTimeImmutable;
use DateTimeZone;
use OpenPhonebook\I18n\Translator;
use OpenPhonebook\Operations\OperationsHealthSnapshot;
use OpenPhonebook\Operations\SchedulerHealth;
use Throwable;

final class OperationsHealthView
{
    public static function render(
        Translator $translator,
        OperationsHealthSnapshot $snapshot,
        string $timezone
    ): string {
        $schedulerRows = '';

        foreach ($snapshot->schedulers as $scheduler) {
            $schedulerRows .= self::schedulerRow(
                $translator,
                $scheduler,
                $timezone
            );
        }

        return '<!doctype html><html lang="'
            . Html::escape($translator->locale)
            . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>'
            . Html::escape($translator->get('operations.title'))
            . ' · Open Phonebook</title>'
            . '<style>'
            . 'body{font-family:system-ui,sans-serif;max-width:1100px;'
            . 'margin:5vh auto;padding:20px}'
            . 'nav{display:flex;gap:16px;margin-bottom:24px}'
            . '.card{border:1px solid #bbb;border-radius:8px;'
            . 'padding:16px;margin:16px 0}'
            . 'table{border-collapse:collapse;width:100%}'
            . 'th,td{text-align:left;padding:8px;border-bottom:1px solid #ddd}'
            . 'code{white-space:nowrap}'
            . '</style></head><body><main>'
            . '<nav><a href="/">'
            . Html::escape($translator->get('nav.dashboard'))
            . '</a></nav><h1>'
            . Html::escape($translator->get('operations.title'))
            . '</h1><section class="card"><h2>'
            . Html::escape($translator->get('operations.database'))
            . '</h2><p>'
            . Html::escape(
                $translator->get(
                    $snapshot->databaseHealthy
                        ? 'operations.status.healthy'
                        : 'operations.status.failed'
                )
            )
            . '</p></section><section class="card"><h2>'
            . Html::escape($translator->get('operations.schedulers'))
            . '</h2><div style="overflow-x:auto"><table><thead><tr><th>'
            . Html::escape($translator->get('operations.task'))
            . '</th><th>'
            . Html::escape($translator->get('devices.status'))
            . '</th><th>'
            . Html::escape($translator->get('operations.last_started'))
            . '</th><th>'
            . Html::escape($translator->get('operations.last_success'))
            . '</th><th>'
            . Html::escape($translator->get('operations.last_failure'))
            . '</th><th>'
            . Html::escape($translator->get('operations.last_counts'))
            . '</th><th>'
            . Html::escape($translator->get('operations.stale_after'))
            . '</th></tr></thead><tbody>'
            . $schedulerRows
            . '</tbody></table></div></section>'
            . '<section class="card"><h2>'
            . Html::escape($translator->get('operations.notification_queue'))
            . '</h2><p>'
            . Html::escape($translator->get('notifications.delivery.pending'))
            . ': ' . $snapshot->notificationQueue['pending']
            . ' · '
            . Html::escape(
                $translator->get('notifications.delivery.processing')
            )
            . ': ' . $snapshot->notificationQueue['processing']
            . ' · '
            . Html::escape($translator->get('notifications.delivery.failed'))
            . ': ' . $snapshot->notificationQueue['failed']
            . '</p></section></main></body></html>';
    }

    private static function schedulerRow(
        Translator $translator,
        SchedulerHealth $scheduler,
        string $timezone
    ): string {
        $counts = $scheduler->lastAttempted === null
            ? '—'
            : $scheduler->lastAttempted . ' / '
                . ($scheduler->lastSucceeded ?? 0) . ' / '
                . ($scheduler->lastFailed ?? 0);

        return '<tr><td>'
            . Html::escape(
                $translator->get(
                    'operations.task.' . $scheduler->task->value
                )
            )
            . '</td><td>'
            . Html::escape(
                $translator->get(
                    'operations.status.' . $scheduler->status->value
                )
            )
            . '</td><td>'
            . self::formatNullable($scheduler->lastStartedAt, $timezone)
            . '</td><td>'
            . self::formatNullable($scheduler->lastSucceededAt, $timezone)
            . '</td><td>'
            . self::formatNullable($scheduler->lastFailedAt, $timezone)
            . '</td><td>'
            . Html::escape($counts)
            . '</td><td>'
            . Html::escape(
                (string) $scheduler->staleAfterSeconds
            )
            . ' s</td></tr>';
    }

    private static function formatNullable(
        ?DateTimeImmutable $time,
        string $timezone
    ): string {
        if ($time === null) {
            return '—';
        }

        try {
            $zone = new DateTimeZone($timezone);
        } catch (Throwable) {
            $zone = new DateTimeZone('UTC');
        }

        return Html::escape(
            $time->setTimezone($zone)->format('Y-m-d H:i:s T')
        );
    }

    private function __construct()
    {
    }
}
