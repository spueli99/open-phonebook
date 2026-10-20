<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use RuntimeException;

final class PhpMailTransport implements MailTransportInterface
{
    public function send(
        string $recipient,
        string $subject,
        string $body,
        string $from
    ): void {
        foreach ([$recipient, $subject, $from] as $value) {
            if (
                str_contains($value, "\r")
                || str_contains($value, "\n")
            ) {
                throw new RuntimeException(
                    'Invalid email header value.'
                );
            }
        }

        if (
            !filter_var($recipient, FILTER_VALIDATE_EMAIL)
            || !filter_var($from, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException(
                'Invalid notification email address.'
            );
        }

        $headers = [
            'From: ' . $from,
            'Content-Type: text/plain; charset=UTF-8',
            'MIME-Version: 1.0',
        ];

        if (!mail($recipient, $subject, $body, $headers)) {
            throw new RuntimeException(
                'Email notification could not be sent.'
            );
        }
    }
}
