<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

use PDO;
use PDOStatement;
use RuntimeException;

final class BackupReadinessRepository implements BackupReadinessRepositoryInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function appliedMigrationIds(): array
    {
        if (!$this->tableExists('migrations')) {
            return [];
        }

        $statement = $this->prepare(
            'SELECT migration_id
             FROM migrations
             ORDER BY migration_id ASC'
        );
        $statement->execute();

        $ids = [];

        while (($value = $statement->fetchColumn()) !== false) {
            $ids[] = (string) $value;
        }

        return $ids;
    }

    public function keyDependentRecordCounts(): array
    {
        return [
            'contact_sources' => $this->countIfAvailable(
                'contact_sources'
            ),
            'notification_channels' => $this->countIfAvailable(
                'notification_channels'
            ),
            'notification_retry_payloads' =>
                $this->countIfAvailable(
                    'notification_deliveries',
                    'encrypted_retry_payload IS NOT NULL',
                    'encrypted_retry_payload'
                ),
            'device_credentials' => $this->countIfAvailable(
                'device_credentials',
                'revoked_at IS NULL'
            ),
            'active_invitations' => $this->countIfAvailable(
                'user_invitations',
                'accepted_at IS NULL
                 AND revoked_at IS NULL
                 AND expires_at > UTC_TIMESTAMP()'
            ),
            'active_password_resets' => $this->countIfAvailable(
                'password_reset_requests',
                'used_at IS NULL
                 AND expires_at > UTC_TIMESTAMP()'
            ),
        ];
    }

    private function countIfAvailable(
        string $table,
        ?string $where = null,
        ?string $requiredColumn = null
    ): int {
        if (!$this->tableExists($table)) {
            return 0;
        }

        if (
            $requiredColumn !== null
            && !$this->columnExists($table, $requiredColumn)
        ) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) FROM ' . $table;

        if ($where !== null) {
            $sql .= ' WHERE ' . $where;
        }

        $statement = $this->database->query($sql);
        $value = $statement === false
            ? false
            : $statement->fetchColumn();

        return $value === false || $value === null
            ? 0
            : (int) $value;
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->prepare(
            'SELECT 1
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
             LIMIT 1'
        );
        $statement->execute(['table_name' => $table]);

        return $statement->fetchColumn() !== false;
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement = $this->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name
             LIMIT 1'
        );
        $statement->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return $statement->fetchColumn() !== false;
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->database->prepare($sql);

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException(
                'Backup readiness query could not be prepared.'
            );
        }

        return $statement;
    }
}
