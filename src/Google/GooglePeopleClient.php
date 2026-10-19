<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

use OpenPhonebook\Http\HttpClientInterface;
use OpenPhonebook\Import\ImportPreviewContact;
use OpenPhonebook\Import\ImportPreviewPhoneNumber;
use RuntimeException;

final class GooglePeopleClient
{
    private const ENDPOINT =
        'https://people.googleapis.com/v1/people/me/connections';

    public function __construct(private readonly HttpClientInterface $http)
    {
    }

    /**
     * @return list<GooglePersonSnapshot>
     */
    public function allConnections(string $accessToken): array
    {
        if ($accessToken === '') {
            throw new RuntimeException('Google access token is missing.');
        }

        $result = [];
        $pageToken = null;

        do {
            $query = [
                'personFields' => implode(',', [
                    'names',
                    'organizations',
                    'biographies',
                    'phoneNumbers',
                ]),
                'pageSize' => '1000',
            ];

            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $response = $this->http->get(
                self::ENDPOINT . '?'
                    . http_build_query(
                        $query,
                        '',
                        '&',
                        PHP_QUERY_RFC3986
                    ),
                [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ]
            );

            if ($response->statusCode !== 200) {
                throw new RuntimeException(
                    'Google People API request failed.'
                );
            }

            $payload = json_decode(
                $response->body,
                true,
                64,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($payload)) {
                throw new RuntimeException(
                    'Google People API response is invalid.'
                );
            }

            $connections = $payload['connections'] ?? [];

            if (!is_array($connections)) {
                throw new RuntimeException(
                    'Google People API response is invalid.'
                );
            }

            foreach ($connections as $person) {
                if (!is_array($person)) {
                    continue;
                }

                $externalId = $person['resourceName'] ?? null;

                if (!is_string($externalId) || $externalId === '') {
                    continue;
                }

                $result[] = new GooglePersonSnapshot(
                    $externalId,
                    $this->mapContact($person)
                );
            }

            $nextPageToken = $payload['nextPageToken'] ?? null;

            if (
                $nextPageToken !== null
                && !is_string($nextPageToken)
            ) {
                throw new RuntimeException(
                    'Google People API pagination is invalid.'
                );
            }

            $pageToken = $nextPageToken === '' ? null : $nextPageToken;
        } while ($pageToken !== null);

        return $result;
    }

    /**
     * @param array<string, mixed> $person
     */
    private function mapContact(array $person): ?ImportPreviewContact
    {
        $name = $this->firstArrayItem($person, 'names');
        $givenName = $this->stringValue($name, 'givenName');
        $familyName = $this->stringValue($name, 'familyName');
        $displayName = $this->stringValue($name, 'displayName');

        $organization = $this->firstArrayItem(
            $person,
            'organizations'
        );
        $organizationName = $this->stringValue(
            $organization,
            'name'
        );

        $biography = $this->firstArrayItem($person, 'biographies');
        $notes = $this->stringValue($biography, 'value');

        if ($displayName === null) {
            $displayName = trim(
                implode(
                    ' ',
                    array_values(
                        array_filter(
                            [$givenName, $familyName],
                            static fn (?string $value): bool =>
                                $value !== null
                        )
                    )
                )
            );
        }

        if ($displayName === '' && $organizationName !== null) {
            $displayName = $organizationName;
        }

        if ($displayName === '') {
            return null;
        }

        $phoneNumbers = [];
        $phones = $person['phoneNumbers'] ?? [];

        if (is_array($phones)) {
            foreach ($phones as $phone) {
                if (!is_array($phone)) {
                    continue;
                }

                $value = $this->stringValue($phone, 'value');

                if ($value === null) {
                    continue;
                }

                $label = $this->stringValue(
                    $phone,
                    'formattedType'
                ) ?? $this->stringValue($phone, 'type');

                $phoneNumbers[] = new ImportPreviewPhoneNumber(
                    $label,
                    $value
                );
            }
        }

        return new ImportPreviewContact(
            $displayName,
            $givenName,
            $familyName,
            $organizationName,
            $notes,
            $phoneNumbers,
        );
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function firstArrayItem(
        array $source,
        string $key
    ): array {
        $value = $source[$key] ?? null;

        if (
            !is_array($value)
            || !isset($value[0])
            || !is_array($value[0])
        ) {
            return [];
        }

        return $value[0];
    }

    /**
     * @param array<string, mixed> $source
     */
    private function stringValue(
        array $source,
        string $key
    ): ?string {
        $value = $source[$key] ?? null;

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
