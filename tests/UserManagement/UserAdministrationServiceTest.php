<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\UserManagement;

use OpenPhonebook\Tests\Support\UserManagement\InMemoryUserAdministrationRepository;
use OpenPhonebook\UserManagement\UserAdministrationService;
use OpenPhonebook\UserManagement\UserSummary;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UserAdministrationServiceTest extends TestCase
{
    public function testSystemAdminCanDeactivateAnotherMember(): void
    {
        $repository = new InMemoryUserAdministrationRepository([
            1 => $this->user(1, 'system_admin', true),
            2 => $this->user(2, 'member', true),
        ]);
        $service = new UserAdministrationService($repository);

        $service->setActive(2, false, 1);

        self::assertFalse($repository->find(2)?->isActive);
    }

    public function testUserCannotDeactivateOwnAccount(): void
    {
        $repository = new InMemoryUserAdministrationRepository([
            1 => $this->user(1, 'system_admin', true),
        ]);
        $service = new UserAdministrationService($repository);

        $this->expectException(RuntimeException::class);
        $service->setActive(1, false, 1);
    }

    public function testLastActiveSystemAdminCannotBeDeactivated(): void
    {
        $repository = new InMemoryUserAdministrationRepository([
            1 => $this->user(1, 'system_admin', true),
            2 => $this->user(2, 'member', true),
        ]);
        $service = new UserAdministrationService($repository);

        $this->expectException(RuntimeException::class);
        $service->setActive(1, false, 99);
    }

    public function testUnchangedStatusIsNoOp(): void
    {
        $repository = new InMemoryUserAdministrationRepository([
            1 => $this->user(1, 'system_admin', true),
        ]);
        $service = new UserAdministrationService($repository);

        $service->setActive(1, true, 1);

        self::assertTrue($repository->find(1)?->isActive);
    }

    private function user(
        int $id,
        string $role,
        bool $active
    ): UserSummary {
        return new UserSummary(
            $id,
            'user' . $id . '@example.com',
            'User ' . $id,
            $role,
            $active,
            false,
            '2026-10-19 12:00:00',
        );
    }
}
