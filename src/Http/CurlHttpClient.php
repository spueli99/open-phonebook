<?php

declare(strict_types=1);

namespace OpenPhonebook\Http;

use RuntimeException;

final class CurlHttpClient implements HttpClientInterface
{
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

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $responseBody = curl_exec($handle);

        if (!is_string($responseBody)) {
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
