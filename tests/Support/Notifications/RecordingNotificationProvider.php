<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Notifications;

use OpenPhonebook\Notifications\NotificationMessage;
use OpenPhonebook\Notifications\NotificationProviderInterface;
use RuntimeException;

final class RecordingNotificationProvider
    implements NotificationProviderInterface
{
    /**
     * @var list<array{
     *     config: array<string, string>,
     *     message: NotificationMessage
     * }>
     */
    public array $sent = [];

    public function __construct(
        private readonly string $providerName,
        private readonly bool $fail = false,
    ) {
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function send(
        array $config,
        NotificationMessage $message
    ): void {
        if ($this->fail) {
            throw new RuntimeException('Provider failed.');
        }

        $this->sent[] = [
            'config' => $config,
            'message' => $message,
        ];
    }
}
