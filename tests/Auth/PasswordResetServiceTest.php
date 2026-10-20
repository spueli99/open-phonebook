<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Auth;

use DateInterval;
use OpenPhonebook\Auth\PasswordResetMailer;
use OpenPhonebook\Auth\PasswordResetService;
use OpenPhonebook\Auth\UserRecord;
use OpenPhonebook\Tests\Support\Auth\InMemoryPasswordResetRepository;
use OpenPhonebook\Tests\Support\Auth\InMemoryUserRepository;
use OpenPhonebook\Tests\Support\Clock\FrozenClock;
use OpenPhonebook\Tests\Support\Database\InMemoryTransactionManager;
use OpenPhonebook\Tests\Support\Notifications\RecordingMailTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PasswordResetServiceTest extends TestCase
{
    private const APP_KEY =
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testActiveUserReceivesSingleUseResetLink(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $users = new InMemoryUserRepository($this->user());
        $resets = new InMemoryPasswordResetRepository();
        $mail = new RecordingMailTransport();
        $service = $this->service(
            $users,
            $resets,
            $mail,
            $clock
        );

        $service->request('USER@example.com');

        self::assertSame(1, $resets->count());
        self::assertCount(1, $mail->messages);
        self::assertSame(
            'user@example.com',
            $mail->messages[0]['recipient']
        );

        $token = $this->tokenFromBody(
            $mail->messages[0]['body']
        );
        $request = $service->resolve($token);

        self::assertNotNull($request);

        $service->complete(
            $request,
            'a sufficiently long new password'
        );

        self::assertTrue($users->resetPasswordCalled);
        self::assertNotNull($users->updatedPasswordHash);
        self::assertTrue(
            password_verify(
                'a sufficiently long new password',
                $users->updatedPasswordHash
            )
        );
        self::assertNull($service->resolve($token));
        self::assertSame(
            2,
            $users->findById(10)?->authRevision
        );
    }

    public function testUnknownInactiveAndDeletedUsersDoNotCreateReset(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');

        foreach ([
            null,
            $this->user(isActive: false),
            $this->user(isDeleted: true),
        ] as $record) {
            $resets = new InMemoryPasswordResetRepository();
            $mail = new RecordingMailTransport();
            $service = $this->service(
                new InMemoryUserRepository($record),
                $resets,
                $mail,
                $clock
            );

            $service->request('user@example.com');

            self::assertSame(0, $resets->count());
            self::assertSame([], $mail->messages);
        }
    }

    public function testRecentRequestSuppressesDuplicateMail(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $resets = new InMemoryPasswordResetRepository();
        $mail = new RecordingMailTransport();
        $service = $this->service(
            new InMemoryUserRepository($this->user()),
            $resets,
            $mail,
            $clock
        );

        $service->request('user@example.com');
        $service->request('user@example.com');

        self::assertSame(1, $resets->count());
        self::assertCount(1, $mail->messages);
    }

    public function testMailFailureConsumesCreatedToken(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $resets = new InMemoryPasswordResetRepository();
        $service = $this->service(
            new InMemoryUserRepository($this->user()),
            $resets,
            new RecordingMailTransport(true),
            $clock
        );

        $service->request('user@example.com');

        self::assertSame(1, $resets->count());
        self::assertSame(1, $resets->usedCount());
    }

    public function testResetExpiresAfterThirtyMinutes(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $mail = new RecordingMailTransport();
        $service = $this->service(
            new InMemoryUserRepository($this->user()),
            new InMemoryPasswordResetRepository(),
            $mail,
            $clock
        );

        $service->request('user@example.com');
        $token = $this->tokenFromBody(
            $mail->messages[0]['body']
        );
        $clock->advance(new DateInterval('PT31M'));

        self::assertNull($service->resolve($token));
    }

    public function testTooShortPasswordDoesNotConsumeReset(): void
    {
        $clock = new FrozenClock('2026-10-20 10:00:00');
        $mail = new RecordingMailTransport();
        $service = $this->service(
            new InMemoryUserRepository($this->user()),
            new InMemoryPasswordResetRepository(),
            $mail,
            $clock
        );

        $service->request('user@example.com');
        $token = $this->tokenFromBody(
            $mail->messages[0]['body']
        );
        $request = $service->resolve($token);

        self::assertNotNull($request);

        try {
            $service->complete($request, 'short');
            self::fail('Short password should be rejected.');
        } catch (RuntimeException) {
        }

        self::assertNotNull($service->resolve($token));
    }

    public function testRequestWithoutMailerIsGenericNoOp(): void
    {
        $resets = new InMemoryPasswordResetRepository();
        $service = new PasswordResetService(
            new InMemoryUserRepository($this->user()),
            $resets,
            null,
            new InMemoryTransactionManager(),
            new FrozenClock('2026-10-20 10:00:00'),
            self::APP_KEY
        );

        $service->request('user@example.com');

        self::assertSame(0, $resets->count());
    }

    private function service(
        InMemoryUserRepository $users,
        InMemoryPasswordResetRepository $resets,
        RecordingMailTransport $mail,
        FrozenClock $clock
    ): PasswordResetService {
        return new PasswordResetService(
            $users,
            $resets,
            new PasswordResetMailer(
                $mail,
                'https://phonebook.example.test',
                'phonebook@example.test'
            ),
            new InMemoryTransactionManager(),
            $clock,
            self::APP_KEY
        );
    }

    private function user(
        bool $isActive = true,
        bool $isDeleted = false
    ): UserRecord {
        return new UserRecord(
            10,
            'user@example.com',
            'User',
            password_hash('old password is long enough', PASSWORD_DEFAULT),
            1,
            'member',
            'en',
            'Europe/Berlin',
            $isActive,
            $isDeleted
        );
    }

    private function tokenFromBody(string $body): string
    {
        $matched = preg_match(
            '~/password-reset/#([a-f0-9]{64})~',
            $body,
            $matches
        );

        self::assertSame(1, $matched);

        return $matches[1];
    }
}
