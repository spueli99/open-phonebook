<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use RuntimeException;

final class NotificationProviderRegistry
{
    /** @var array<string, NotificationProviderInterface> */
    private array $providers = [];

    /**
     * @param list<NotificationProviderInterface> $providers
     */
    public function __construct(array $providers)
    {
        foreach ($providers as $provider) {
            $name = $provider->name();

            if (
                !preg_match('/^[a-z0-9_-]+$/', $name)
                || isset($this->providers[$name])
            ) {
                throw new RuntimeException(
                    'Invalid or duplicate notification provider.'
                );
            }

            $this->providers[$name] = $provider;
        }
    }

    public function get(string $name): ?NotificationProviderInterface
    {
        return $this->providers[$name] ?? null;
    }
}
