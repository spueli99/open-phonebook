<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

final class SecurityHeaders
{
    public static function sendHtml(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
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
