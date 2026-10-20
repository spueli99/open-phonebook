<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Notifications;

use OpenPhonebook\Notifications\MailTransportInterface;
use RuntimeException;

final class RecordingMailTransport implements MailTransportInterface
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

    public function __construct(
        private readonly bool $fail = false
    ) {
    }

    public function send(
        string $recipient,
        string $subject,
        string $body,
        string $from
    ): void {
        if ($this->fail) {
            throw new RuntimeException('Mail transport failed.');
        }

        $this->messages[] = [
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'from' => $from,
        ];
    }
}
