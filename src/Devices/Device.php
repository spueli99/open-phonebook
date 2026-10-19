<?php

declare(strict_types=1);

namespace OpenPhonebook\Devices;

final readonly class Device
{
    public function __construct(
        public int $id,
        public int $ownerUserId,
        public string $name,
        public ?string $vendor,
        public ?string $model,
        public bool $isActive,
    ) {
    }
}
