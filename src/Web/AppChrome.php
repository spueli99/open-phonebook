<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

final class AppChrome
{
    public static function header(): string
    {
        return '<header class="app-header">'
            . '<div class="app-header-inner">'
            . '<a class="app-brand" href="/">Open Phonebook</a>'
            . '</div></header>';
    }

    public static function footer(): string
    {
        return '<footer class="app-footer">'
            . '<div class="app-footer-inner">'
            . '<span>Open Phonebook</span>'
            . '<span aria-hidden="true">·</span>'
            . '<span>Self-hosted contact hub</span>'
            . '</div></footer>';
    }

    private function __construct()
    {
    }
}
