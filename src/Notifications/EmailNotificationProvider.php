<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use RuntimeException;

final class EmailNotificationProvider
    implements NotificationProviderInterface
{
    public function __construct(
        private readonly MailTransportInterface $transport
    ) {
    }

    public function name(): string
    {
        return 'email';
    }

    public function send(
        array $config,
        NotificationMessage $message
    ): void {
        $recipient = $config['recipient'] ?? '';
        $from = $config['from'] ?? '';

        if (
            !filter_var($recipient, FILTER_VALIDATE_EMAIL)
            || !filter_var($from, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException(
                'Invalid email notification configuration.'
            );
        }

        $this->transport->send(
            $recipient,
            $message->title,
            $message->body,
            $from
        );
    }
}
