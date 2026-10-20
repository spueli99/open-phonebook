<?php

declare(strict_types=1);

namespace OpenPhonebook\Database;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @param list<Migration> $migrations
     *
     * @return list<string>
     */
    public function run(array $migrations): array
    {
        $this->ensureMigrationTable();
        $applied = [];

        foreach ($migrations as $migration) {
            if ($this->isApplied($migration->id())) {
                continue;
            }

            try {
                $migration->up($this->database);

                $statement = $this->database->prepare(
                    'INSERT INTO migrations (migration_id, applied_at) VALUES (:migration_id, UTC_TIMESTAMP())'
                );
                $statement->execute(['migration_id' => $migration->id()]);

                $applied[] = $migration->id();
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    sprintf('Migration failed: %s.', $migration->id()),
                    0,
                    $exception
                );
            }
        }

        return $applied;
    }

    private function ensureMigrationTable(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                migration_id VARCHAR(190) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function isApplied(string $id): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM migrations WHERE migration_id = :migration_id LIMIT 1'
        );
        $statement->execute(['migration_id' => $id]);

        return $statement->fetchColumn() !== false;
    }
}
