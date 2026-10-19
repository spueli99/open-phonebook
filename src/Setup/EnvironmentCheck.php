<?php

declare(strict_types=1);

namespace OpenPhonebook\Setup;

final readonly class EnvironmentCheck
{
    public function __construct(
        public string $name,
        public bool $passed,
        public string $message,
    ) {
    }
}
