<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

interface NotificationProviderInterface
{
    public function name(): string;

    /**
     * @param array<string, string> $config
     */
    public function send(
        array $config,
        NotificationMessage $message
    ): void;
}
