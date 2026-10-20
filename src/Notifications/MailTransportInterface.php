<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

interface MailTransportInterface
{
    public function send(
        string $recipient,
        string $subject,
        string $body,
        string $from
    ): void;
}
