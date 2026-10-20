<?php

declare(strict_types=1);

namespace OpenPhonebook\Auth;

use OpenPhonebook\Notifications\MailTransportInterface;
use RuntimeException;

final class PasswordResetMailer
{
    public function __construct(
        private readonly MailTransportInterface $transport,
        private readonly string $baseUrl,
        private readonly string $from,
    ) {
        if (
            !str_starts_with($baseUrl, 'https://')
            || filter_var($baseUrl, FILTER_VALIDATE_URL) === false
            || filter_var($from, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new RuntimeException(
                'Password reset mail configuration is invalid.'
            );
        }
    }

    public function send(
        UserRecord $user,
        string $plainToken
    ): void {
        $link = rtrim($this->baseUrl, '/')
            . '/password-reset/#'
            . $plainToken;
        $german = $user->locale === 'de';

        $subject = $german
            ? 'Open Phonebook: Passwort zurücksetzen'
            : 'Open Phonebook: Reset your password';
        $body = $german
            ? "Für dein Open-Phonebook-Konto wurde ein Passwort-Reset angefordert.\n\n"
                . "Öffne innerhalb von 30 Minuten diesen Link:\n"
                . $link
                . "\n\nWenn du den Reset nicht angefordert hast, kannst du diese E-Mail ignorieren."
            : "A password reset was requested for your Open Phonebook account.\n\n"
                . "Open this link within 30 minutes:\n"
                . $link
                . "\n\nIf you did not request this reset, you can ignore this email.";

        $this->transport->send(
            $user->email,
            $subject,
            $body,
            $this->from
        );
    }
}
