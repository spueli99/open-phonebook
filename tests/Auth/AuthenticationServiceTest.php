<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Auth;

use OpenPhonebook\Auth\AuthenticationService;
use OpenPhonebook\Auth\UserRecord;
use OpenPhonebook\Auth\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class AuthenticationServiceTest extends TestCase
{
    public function testValidActiveUserCanAuthenticate(): void
    {
        $record = $this->record(
            password_hash('correct horse battery staple', PASSWORD_DEFAULT)
        );
        $repository = new InMemoryUserRepository($record);
        $service = new AuthenticationService($repository);

        $result = $service->authenticate(
            'ADMIN@example.com',
            'correct horse battery staple'
        );

        self::assertTrue($result->successful);
        self::assertNotNull($result->user);
        self::assertSame(1, $result->user->id);
        self::assertSame('admin@example.com', $result->user->email);
    }

    public function testWrongPasswordIsRejected(): void
    {
        $record = $this->record(
            password_hash('correct password', PASSWORD_DEFAULT)
        );
        $service = new AuthenticationService(
            new InMemoryUserRepository($record)
        );

        $result = $service->authenticate(
            'admin@example.com',
            'wrong password'
        );

        self::assertFalse($result->successful);
        self::assertNull($result->user);
    }

    public function testInactiveUserIsRejected(): void
    {
        $record = $this->record(
            password_hash('correct password', PASSWORD_DEFAULT),
            isActive: false
        );
        $service = new AuthenticationService(
            new InMemoryUserRepository($record)
        );

        $result = $service->authenticate(
            'admin@example.com',
            'correct password'
        );

        self::assertFalse($result->successful);
    }

    public function testDeletedUserIsRejected(): void
    {
        $record = $this->record(
            password_hash('correct password', PASSWORD_DEFAULT),
            isDeleted: true
        );
        $service = new AuthenticationService(
            new InMemoryUserRepository($record)
        );

        $result = $service->authenticate(
            'admin@example.com',
            'correct password'
        );

        self::assertFalse($result->successful);
    }

    public function testUnknownUserIsRejected(): void
    {
        $service = new AuthenticationService(
            new InMemoryUserRepository(null)
        );

        $result = $service->authenticate(
            'nobody@example.com',
            'anything'
        );

        self::assertFalse($result->successful);
    }

    private function record(
        string $passwordHash,
        bool $isActive = true,
        bool $isDeleted = false
    ): UserRecord {
        return new UserRecord(
            1,
            'admin@example.com',
            'Administrator',
            $passwordHash,
            'system_admin',
            'en',
            'UTC',
            $isActive,
            $isDeleted,
        );
    }
}

final class InMemoryUserRepository implements UserRepositoryInterface
{
    public ?string $updatedPasswordHash = null;

    public function __construct(private readonly ?UserRecord $record)
    {
    }

    public function findByEmail(string $email): ?UserRecord
    {
        if ($this->record === null) {
            return null;
        }

        return strtolower(trim($email)) === $this->record->email
            ? $this->record
            : null;
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $this->updatedPasswordHash = $passwordHash;
    }
}
