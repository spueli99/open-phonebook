<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Http;

use OpenPhonebook\Http\HttpClientInterface;
use OpenPhonebook\Http\HttpResponse;
use RuntimeException;

final class FakeHttpClient implements HttpClientInterface
{
    /**
     * @var list<HttpResponse>
     */
    private array $responses = [];

    /**
     * @var list<array{
     *     method: string,
     *     url: string,
     *     headers: array<string, string>,
     *     form: array<string, string>
     * }>
     */
    public array $requests = [];

    public function queue(HttpResponse $response): void
    {
        $this->responses[] = $response;
    }

    public function get(string $url, array $headers = []): HttpResponse
    {
        $this->requests[] = [
            'method' => 'GET',
            'url' => $url,
            'headers' => $headers,
            'form' => [],
        ];

        return $this->next();
    }

    public function postForm(
        string $url,
        array $headers,
        array $form
    ): HttpResponse {
        $this->requests[] = [
            'method' => 'POST',
            'url' => $url,
            'headers' => $headers,
            'form' => $form,
        ];

        return $this->next();
    }

    private function next(): HttpResponse
    {
        $response = array_shift($this->responses);

        if (!$response instanceof HttpResponse) {
            throw new RuntimeException('No fake HTTP response queued.');
        }

        return $response;
    }
}
