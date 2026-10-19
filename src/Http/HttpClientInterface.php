<?php

declare(strict_types=1);

namespace OpenPhonebook\Http;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): HttpResponse;

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $form
     */
    public function postForm(
        string $url,
        array $headers,
        array $form
    ): HttpResponse;
}
