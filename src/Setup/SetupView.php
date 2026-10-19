<?php

declare(strict_types=1);

namespace OpenPhonebook\Setup;

final class SetupView
{
    /**
     * @param list<string> $errors
     */
    public static function page(
        string $title,
        string $body,
        array $errors = [],
        string $language = 'en'
    ): string {
        $errorHtml = '';

        if ($errors !== []) {
            $items = '';
            foreach ($errors as $error) {
                $items .= '<li>' . self::escape($error) . '</li>';
            }

            $errorHtml = '<div class="alert" role="alert"><strong>Installation cannot continue.</strong><ul>'
                . $items
                . '</ul></div>';
        }

        return '<!doctype html>'
            . '<html lang="' . self::escape($language) . '">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>' . self::escape($title) . ' · Open Phonebook</title>'
            . '<style>'
            . ':root{color-scheme:light dark;font-family:system-ui,sans-serif}'
            . 'body{margin:0;background:#f4f6f8;color:#18212b}'
            . 'main{max-width:760px;margin:6vh auto;padding:0 20px}'
            . '.card{background:#fff;border:1px solid #dce2e8;border-radius:14px;padding:28px;box-shadow:0 8px 30px rgba(0,0,0,.06)}'
            . 'h1{margin-top:0}label{display:block;font-weight:600;margin-top:16px}'
            . 'input,select{box-sizing:border-box;width:100%;padding:11px 12px;margin-top:6px;border:1px solid #b8c2cc;border-radius:8px;background:#fff;color:#18212b}'
            . 'button{margin-top:22px;padding:11px 18px;border:0;border-radius:8px;background:#1f6feb;color:#fff;font-weight:700;cursor:pointer}'
            . '.muted{color:#5f6b76}.ok{color:#18794e}.fail{color:#b42318}.check{padding:7px 0;border-bottom:1px solid #eef1f4}'
            . '.alert{background:#fff0f0;border:1px solid #f2b8b5;padding:14px 16px;border-radius:8px;margin-bottom:18px}'
            . '@media(prefers-color-scheme:dark){body{background:#11161c;color:#e6edf3}.card{background:#161b22;border-color:#30363d}.muted{color:#9da7b1}input,select{background:#0d1117;color:#e6edf3;border-color:#484f58}.check{border-color:#30363d}}'
            . '</style>'
            . '</head><body><main><div class="card">'
            . '<p class="muted">Open Phonebook setup</p>'
            . '<h1>' . self::escape($title) . '</h1>'
            . $errorHtml
            . $body
            . '</div></main></body></html>';
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function __construct()
    {
    }
}
