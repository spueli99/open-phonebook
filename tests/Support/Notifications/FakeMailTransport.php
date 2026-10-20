<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Notifications;

use OpenPhonebook\Notifications\MailTransportInterface;

final class FakeMailTransport implements MailTransportInterface
{
    /**
     * @var list<array{
     *     recipient: string,
     *     subject: string,
     *     body: string,
     *     from: string
     * }>
     */
    public array $messages = [];

    public function send(
        string $recipient,
        string $subject,
        string $body,
        string $from
    ): void {
        $this->messages[] = [
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'from' => $from,
        ];
    }
}
