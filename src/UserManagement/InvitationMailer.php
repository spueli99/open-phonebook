<?php

declare(strict_types=1);

namespace OpenPhonebook\UserManagement;

use OpenPhonebook\Notifications\MailTransportInterface;
use RuntimeException;

final class InvitationMailer
{
    public function __construct(
        private readonly MailTransportInterface $transport,
        private readonly string $baseUrl,
        private readonly string $from,
        private readonly string $locale,
    ) {
        if (
            !str_starts_with($baseUrl, 'https://')
            || filter_var($baseUrl, FILTER_VALIDATE_URL) === false
            || filter_var($from, FILTER_VALIDATE_EMAIL) === false
            || !in_array($locale, ['en', 'de'], true)
        ) {
            throw new RuntimeException(
                'Invitation mail configuration is invalid.'
            );
        }
    }

    public function send(InvitationCreationResult $invitation): void
    {
        $link = rtrim($this->baseUrl, '/')
            . '/invite/accept/#'
            . $invitation->token;
        $german = $this->locale === 'de';

        $subject = $german
            ? 'Open Phonebook: Einladung'
            : 'Open Phonebook: Invitation';
        $body = $german
            ? "Du wurdest zu Open Phonebook eingeladen.\n\n"
                . "Öffne innerhalb von drei Tagen diesen Link, um dein Konto einzurichten:\n"
                . $link
                . "\n\nWenn du diese Einladung nicht erwartest, kannst du diese E-Mail ignorieren."
            : "You have been invited to Open Phonebook.\n\n"
                . "Open this link within three days to set up your account:\n"
                . $link
                . "\n\nIf you did not expect this invitation, you can ignore this email.";

        $this->transport->send(
            $invitation->email,
            $subject,
            $body,
            $this->from
        );
    }
}
