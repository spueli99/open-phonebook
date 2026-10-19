<?php

declare(strict_types=1);

namespace OpenPhonebook\Database;

interface TransactionManagerInterface
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed;
}
