<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use DateTimeZone;
use OpenPhonebook\Audit\AuditEvent;
use OpenPhonebook\I18n\Translator;
use Throwable;

final class AuditLogView
{
    /**
     * @param list<AuditEvent> $events
     */
    public static function render(
        Translator $translator,
        array $events,
        string $timezone
    ): string {
        $rows = '';

        foreach ($events as $event) {
            $rows .= self::row($translator, $event, $timezone);
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6">'
                . Html::escape($translator->get('audit.none'))
                . '</td></tr>';
        }

        return '<!doctype html><html lang="'
            . Html::escape($translator->locale)
            . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>'
            . Html::escape($translator->get('audit.title'))
            . ' · Open Phonebook</title><style>'
            . 'body{font-family:system-ui,sans-serif;max-width:1200px;'
            . 'margin:5vh auto;padding:20px}'
            . 'nav{display:flex;gap:16px;margin-bottom:24px}'
            . 'table{border-collapse:collapse;width:100%}'
            . 'th,td{text-align:left;padding:8px;border-bottom:1px solid #ddd;'
            . 'vertical-align:top}'
            . 'code{white-space:nowrap}'
            . '</style></head><body><main><nav><a href="/">'
            . Html::escape($translator->get('nav.dashboard'))
            . '</a><a href="/admin/operations/">'
            . Html::escape($translator->get('nav.operations'))
            . '</a></nav><h1>'
            . Html::escape($translator->get('audit.title'))
            . '</h1><p>'
            . Html::escape($translator->get('audit.help'))
            . '</p><div style="overflow-x:auto"><table><thead><tr><th>'
            . Html::escape($translator->get('audit.time'))
            . '</th><th>'
            . Html::escape($translator->get('audit.event'))
            . '</th><th>'
            . Html::escape($translator->get('audit.actor'))
            . '</th><th>'
            . Html::escape($translator->get('audit.target'))
            . '</th><th>'
            . Html::escape($translator->get('audit.metadata'))
            . '</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table></div></main></body></html>';
    }

    private static function row(
        Translator $translator,
        AuditEvent $event,
        string $timezone
    ): string {
        $metadata = [];

        foreach ($event->metadata as $key => $value) {
            $metadata[] = $key . '=' . self::scalar($value);
        }

        $actor = $event->actorUserId === null
            ? $translator->get('audit.actor.deleted')
            : '#' . $event->actorUserId;

        return '<tr><td>'
            . Html::escape(self::formatTime($event, $timezone))
            . '</td><td><code>'
            . Html::escape($event->type->value)
            . '</code></td><td>'
            . Html::escape($actor)
            . '</td><td><code>'
            . Html::escape($event->targetType->value)
            . '</code> #'
            . Html::escape((string) $event->targetId)
            . '</td><td>'
            . Html::escape($metadata === [] ? '—' : implode(', ', $metadata))
            . '</td></tr>';
    }

    private static function formatTime(
        AuditEvent $event,
        string $timezone
    ): string {
        try {
            $zone = new DateTimeZone($timezone);
        } catch (Throwable) {
            $zone = new DateTimeZone('UTC');
        }

        return $event->createdAt
            ->setTimezone($zone)
            ->format('Y-m-d H:i:s T');
    }

    private static function scalar(bool|int|string $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function __construct()
    {
    }
}
