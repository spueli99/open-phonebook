<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

final class SecurityHeaders
{
    public static function sendPrivate(): void
    {
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
    }

    public static function sendHtml(): void
    {
        self::sendPrivate();
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: DENY');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header(
            "Content-Security-Policy: default-src 'none'; "
            . "style-src 'unsafe-inline'; "
            . "script-src 'self'; "
            . "worker-src 'self'; "
            . "connect-src 'self'; "
            . "manifest-src 'self'; "
            . "img-src 'self'; "
            . "form-action 'self'; "
            . "base-uri 'none'; "
            . "frame-ancestors 'none'"
        );
    }

    private function __construct()
    {
    }
}
