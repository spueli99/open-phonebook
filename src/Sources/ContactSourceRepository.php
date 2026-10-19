<?php

declare(strict_types=1);

namespace OpenPhonebook\Sources;

use PDO;
use RuntimeException;

final class ContactSourceRepository implements ContactSourceRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function findGoogleForAddressBook(
        int $ownerUserId,
        int $addressBookId
    ): ?ContactSource {
        $statement = $this->database->prepare(
            "SELECT id, owner_user_id, address_book_id, provider,
                    encrypted_refresh_token, encrypted_sync_token
             FROM contact_sources
             WHERE owner_user_id = :owner_user_id
               AND address_book_id = :address_book_id
               AND provider = 'google'
             LIMIT 1"
        );
        $statement->execute([
            'owner_user_id' => $ownerUserId,
            'address_book_id' => $addressBookId,
        ]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException(
                'Unexpected contact source query result.'
            );
        }

        return $this->hydrateSource($row);
    }

    public function allGoogleSources(): array
    {
        $statement = $this->database->query(
            "SELECT id, owner_user_id, address_book_id, provider,
                    encrypted_refresh_token, encrypted_sync_token
             FROM contact_sources
             WHERE provider = 'google'
             ORDER BY id ASC"
        );

        if ($statement === false) {
            throw new RuntimeException(
                'Google contact sources could not be loaded.'
            );
        }

        $sources = [];

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException(
                    'Unexpected contact source query result.'
                );
            }

            $sources[] = $this->hydrateSource($row);
        }

        return $sources;
    }

    public function upsertGoogleSource(
        int $ownerUserId,
        int $addressBookId,
        string $encryptedRefreshToken
    ): ContactSource {
        $statement = $this->database->prepare(
            "INSERT INTO contact_sources (
                owner_user_id,
                address_book_id,
                provider,
                encrypted_refresh_token,
                created_at,
                updated_at
             ) VALUES (
                :owner_user_id,
                :address_book_id,
                'google',
                :encrypted_refresh_token,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )
             ON DUPLICATE KEY UPDATE
                encrypted_refresh_token = VALUES(encrypted_refresh_token),
                encrypted_sync_token = NULL,
                updated_at = UTC_TIMESTAMP()"
        );
        $statement->execute([
            'owner_user_id' => $ownerUserId,
            'address_book_id' => $addressBookId,
            'encrypted_refresh_token' => $encryptedRefreshToken,
        ]);

        $source = $this->findGoogleForAddressBook(
            $ownerUserId,
            $addressBookId
        );

        if ($source === null) {
            throw new RuntimeException(
                'Google contact source could not be loaded.'
            );
        }

        return $source;
    }

    public function recordsForSource(int $sourceId): array
    {
        $statement = $this->database->prepare(
            'SELECT external_id, contact_id
             FROM source_records
             WHERE source_id = :source_id
             ORDER BY external_id ASC'
        );
        $statement->execute(['source_id' => $sourceId]);

        $records = [];

        while (($row = $statement->fetch()) !== false) {
            if (!is_array($row)) {
                throw new RuntimeException(
                    'Unexpected source record query result.'
                );
            }

            $records[] = new SourceRecord(
                (string) $row['external_id'],
                (int) $row['contact_id'],
            );
        }

        return $records;
    }

    public function upsertRecord(
        int $sourceId,
        string $externalId,
        int $contactId
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO source_records (
                source_id,
                external_id,
                contact_id,
                created_at,
                updated_at
             ) VALUES (
                :source_id,
                :external_id,
                :contact_id,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )
             ON DUPLICATE KEY UPDATE
                contact_id = VALUES(contact_id),
                updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            'source_id' => $sourceId,
            'external_id' => $externalId,
            'contact_id' => $contactId,
        ]);
    }

    public function deleteRecord(
        int $sourceId,
        string $externalId
    ): void {
        $statement = $this->database->prepare(
            'DELETE FROM source_records
             WHERE source_id = :source_id
               AND external_id = :external_id'
        );
        $statement->execute([
            'source_id' => $sourceId,
            'external_id' => $externalId,
        ]);
    }

    public function markSynced(
        int $sourceId,
        ?string $encryptedSyncToken
    ): void {
        $statement = $this->database->prepare(
            'UPDATE contact_sources
             SET encrypted_sync_token = :encrypted_sync_token,
                 last_synced_at = UTC_TIMESTAMP(),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $sourceId,
            'encrypted_sync_token' => $encryptedSyncToken,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateSource(array $row): ContactSource
    {
        return new ContactSource(
            (int) $row['id'],
            (int) $row['owner_user_id'],
            (int) $row['address_book_id'],
            (string) $row['provider'],
            (string) $row['encrypted_refresh_token'],
            isset($row['encrypted_sync_token'])
                ? (string) $row['encrypted_sync_token']
                : null,
        );
    }
}
