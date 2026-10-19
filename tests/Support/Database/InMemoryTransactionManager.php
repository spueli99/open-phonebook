<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Database;

use OpenPhonebook\Database\TransactionManagerInterface;

final class InMemoryTransactionManager implements TransactionManagerInterface
{
    public int $calls = 0;

    public function transactional(callable $callback): mixed
    {
        $this->calls++;

        return $callback();
    }
}
