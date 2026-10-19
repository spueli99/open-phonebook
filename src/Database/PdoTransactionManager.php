<?php

declare(strict_types=1);

namespace OpenPhonebook\Database;

use PDO;
use RuntimeException;
use Throwable;

final class PdoTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function transactional(callable $callback): mixed
    {
        if ($this->database->inTransaction()) {
            throw new RuntimeException(
                'Nested transaction manager calls are not supported.'
            );
        }

        $this->database->beginTransaction();

        try {
            $result = $callback();
            $this->database->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }
}
