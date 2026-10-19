<?php

declare(strict_types=1);

namespace OpenPhonebook\Http;

use RuntimeException;

final class CurlHttpClient implements HttpClientInterface
{
    private const MAX_RESPONSE_BYTES = 16_000_000;
    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->request('GET', $url, $headers, null);
    }

    public function postForm(
        string $url,
        array $headers,
        array $form
    ): HttpResponse {
        return $this->request(
            'POST',
            $url,
            array_merge(
                ['Content-Type' => 'application/x-www-form-urlencoded'],
                $headers
            ),
            http_build_query($form, '', '&', PHP_QUERY_RFC3986)
        );
    }

    /**
     * @param non-empty-string $method
     * @param array<string, string> $headers
     */
    private function request(
        string $method,
        string $url,
        array $headers,
        ?string $body
    ): HttpResponse {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('Unable to initialize HTTP request.');
        }

        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $responseBody = '';

        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headerLines);
        curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt(
            $handle,
            CURLOPT_WRITEFUNCTION,
            static function (
                \CurlHandle $curl,
                string $chunk
            ) use (&$responseBody): int {
                if (
                    strlen($responseBody) + strlen($chunk)
                    > self::MAX_RESPONSE_BYTES
                ) {
                    return 0;
                }

                $responseBody .= $chunk;

                return strlen($chunk);
            }
        );

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $success = curl_exec($handle);

        if ($success === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new RuntimeException(
                'HTTP request failed'
                . ($error === '' ? '.' : ': ' . $error)
            );
        }

        $statusCode = (int) curl_getinfo(
            $handle,
            CURLINFO_RESPONSE_CODE
        );
        curl_close($handle);

        return new HttpResponse($statusCode, $responseBody);
    }
}
