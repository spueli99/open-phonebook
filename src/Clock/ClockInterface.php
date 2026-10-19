<?php

declare(strict_types=1);

namespace OpenPhonebook\Clock;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
