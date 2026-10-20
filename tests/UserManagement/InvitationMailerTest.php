<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\UserManagement;

use DateTimeImmutable;
use OpenPhonebook\Auth\Role;
use OpenPhonebook\Tests\Support\Notifications\FakeMailTransport;
use OpenPhonebook\UserManagement\InvitationCreationResult;
use OpenPhonebook\UserManagement\InvitationMailer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationMailerTest extends TestCase
{
    public function testSendsEnglishInvitationWithFragmentToken(): void
    {
        $transport = new FakeMailTransport();
        $mailer = new InvitationMailer(
            $transport,
            'https://phonebook.example.com',
            'phonebook@example.com',
            'en'
        );

        $mailer->send(new InvitationCreationResult(
            1,
            'user@example.com',
            Role::Member,
            str_repeat('a', 64),
            new DateTimeImmutable('2026-10-23 12:00:00')
        ));

        self::assertCount(1, $transport->messages);
        self::assertSame(
            'user@example.com',
            $transport->messages[0]['recipient']
        );
        self::assertStringContainsString(
            '/invite/accept/#' . str_repeat('a', 64),
            $transport->messages[0]['body']
        );
    }

    public function testRejectsNonHttpsBaseUrl(): void
    {
        $this->expectException(RuntimeException::class);

        new InvitationMailer(
            new FakeMailTransport(),
            'http://phonebook.example.com',
            'phonebook@example.com',
            'en'
        );
    }
}
