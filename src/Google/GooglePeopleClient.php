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

    /**
     * @var list<string>
     */
    private const PERSON_FIELDS = [
        'names',
        'organizations',
        'biographies',
        'phoneNumbers',
        'metadata',
    ];

    public function __construct(private readonly HttpClientInterface $http)
    {
    }

    /**
     * Backward-compatible full contact list without exposing sync metadata.
     *
     * @return list<GooglePersonSnapshot>
     */
    public function allConnections(string $accessToken): array
    {
        return $this->fullSnapshot($accessToken)->people;
    }

    public function fullSnapshot(
        string $accessToken
    ): GooglePeopleSyncBatch {
        return $this->listConnections(
            $accessToken,
            null,
            true
        );
    }

    public function changesSince(
        string $accessToken,
        string $syncToken
    ): GooglePeopleSyncBatch {
        if ($syncToken === '') {
            throw new RuntimeException('Google sync token is missing.');
        }

        return $this->listConnections(
            $accessToken,
            $syncToken,
            false
        );
    }

    private function listConnections(
        string $accessToken,
        ?string $syncToken,
        bool $requestSyncToken
    ): GooglePeopleSyncBatch {
        if ($accessToken === '') {
            throw new RuntimeException('Google access token is missing.');
        }

        $result = [];
        $pageToken = null;
        $nextSyncToken = null;

        do {
            $query = [
                'personFields' => implode(',', self::PERSON_FIELDS),
                'pageSize' => '1000',
            ];

            if ($syncToken !== null) {
                $query['syncToken'] = $syncToken;
            } elseif ($requestSyncToken) {
                $query['requestSyncToken'] = 'true';
            }

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

            if ($response->statusCode === 410 && $syncToken !== null) {
                throw new GoogleSyncTokenExpired(
                    'Google sync token has expired.'
                );
            }

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

                $snapshot = $this->mapSnapshot($person);

                if ($snapshot !== null) {
                    $result[] = $snapshot;
                }
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

            if ($pageToken === null) {
                $candidateSyncToken = $payload['nextSyncToken'] ?? null;

                if (
                    !is_string($candidateSyncToken)
                    || $candidateSyncToken === ''
                ) {
                    throw new RuntimeException(
                        'Google People API did not return a sync token.'
                    );
                }

                $nextSyncToken = $candidateSyncToken;
            }
        } while ($pageToken !== null);

        if ($nextSyncToken === null) {
            throw new RuntimeException(
                'Google People API did not return a sync token.'
            );
        }

        return new GooglePeopleSyncBatch(
            $result,
            $nextSyncToken,
            $syncToken === null
        );
    }

    /**
     * @param array<string, mixed> $person
     */
    private function mapSnapshot(
        array $person
    ): ?GooglePersonSnapshot {
        $externalId = $person['resourceName'] ?? null;

        if (
            !is_string($externalId)
            || $externalId === ''
            || mb_strlen($externalId) > 255
        ) {
            return null;
        }

        $metadata = $person['metadata'] ?? [];
        $deleted = false;
        $previousExternalIds = [];

        if (is_array($metadata)) {
            $deleted = ($metadata['deleted'] ?? false) === true;
            $previous = $metadata['previousResourceNames'] ?? [];

            if (is_array($previous)) {
                foreach ($previous as $previousExternalId) {
                    if (
                        is_string($previousExternalId)
                        && $previousExternalId !== ''
                        && mb_strlen($previousExternalId) <= 255
                    ) {
                        $previousExternalIds[] = $previousExternalId;
                    }
                }
            }
        }

        return new GooglePersonSnapshot(
            $externalId,
            $deleted ? null : $this->mapContact($person),
            $deleted,
            array_values(array_unique($previousExternalIds)),
        );
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
            $nameParts = [];

            if ($givenName !== null) {
                $nameParts[] = $givenName;
            }

            if ($familyName !== null) {
                $nameParts[] = $familyName;
            }

            $displayName = implode(' ', $nameParts);
        }

        if ($displayName === '' && $organizationName !== null) {
            $displayName = $organizationName;
        }

        if (
            $displayName === ''
            || mb_strlen($displayName) > 255
            || ($givenName !== null && mb_strlen($givenName) > 120)
            || ($familyName !== null && mb_strlen($familyName) > 120)
            || (
                $organizationName !== null
                && mb_strlen($organizationName) > 255
            )
            || ($notes !== null && mb_strlen($notes) > 10_000)
        ) {
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

                if ($value === null || mb_strlen($value) > 100) {
                    continue;
                }

                $label = $this->stringValue(
                    $phone,
                    'formattedType'
                ) ?? $this->stringValue($phone, 'type');

                if ($label !== null && mb_strlen($label) > 64) {
                    $label = null;
                }

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
