<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\UserManagement;

use OpenPhonebook\Audit\AuditEventType;
use OpenPhonebook\Tests\Support\Audit\RecordingAuditEventWriter;
use OpenPhonebook\Tests\Support\Database\InMemoryTransactionManager;
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
        $audit = new RecordingAuditEventWriter();
        $transactions = new InMemoryTransactionManager();
        $service = new UserAdministrationService(
            $repository,
            $audit,
            $transactions
        );

        $service->setActive(2, false, 1);

        self::assertFalse($repository->find(2)?->isActive);
        self::assertSame(1, $transactions->calls);
        self::assertCount(1, $audit->events);
        self::assertSame(
            AuditEventType::UserDeactivated,
            $audit->events[0]['type']
        );
        self::assertSame(1, $audit->events[0]['actor_user_id']);
        self::assertSame(2, $audit->events[0]['target_id']);
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
        $audit = new RecordingAuditEventWriter();
        $service = new UserAdministrationService($repository, $audit);

        $service->setActive(1, true, 1);

        self::assertTrue($repository->find(1)?->isActive);
        self::assertSame([], $audit->events);
    }

    public function testSystemAdminCanPromoteMember(): void
    {
        $repository = new InMemoryUserAdministrationRepository([
            1 => $this->user(1, 'system_admin', true),
            2 => $this->user(2, 'member', true),
        ]);
        $audit = new RecordingAuditEventWriter();
        $service = new UserAdministrationService($repository, $audit);

        $service->setRole(2, \OpenPhonebook\Auth\Role::SystemAdmin, 1);

        self::assertSame('system_admin', $repository->find(2)?->role);
        self::assertCount(1, $audit->events);
        self::assertSame(
            AuditEventType::UserRoleChanged,
            $audit->events[0]['type']
        );
        self::assertSame(
            [
                'previous_role' => 'member',
                'new_role' => 'system_admin',
            ],
            $audit->events[0]['metadata']
        );
    }

    public function testSystemAdminCannotRemoveOwnAdminRole(): void
    {
        $repository = new InMemoryUserAdministrationRepository([
            1 => $this->user(1, 'system_admin', true),
            2 => $this->user(2, 'system_admin', true),
        ]);
        $service = new UserAdministrationService($repository);

        $this->expectException(RuntimeException::class);
        $service->setRole(1, \OpenPhonebook\Auth\Role::Member, 1);
    }

    public function testLastActiveSystemAdminCannotBeDemoted(): void
    {
        $repository = new InMemoryUserAdministrationRepository([
            1 => $this->user(1, 'system_admin', true),
            2 => $this->user(2, 'member', true),
        ]);
        $service = new UserAdministrationService($repository);

        $this->expectException(RuntimeException::class);
        $service->setRole(1, \OpenPhonebook\Auth\Role::Member, 99);
    }

    public function testOrganizationAdminRoleCannotBeAssignedWithoutContext(): void
    {
        $repository = new InMemoryUserAdministrationRepository([
            1 => $this->user(1, 'system_admin', true),
            2 => $this->user(2, 'member', true),
        ]);
        $service = new UserAdministrationService($repository);

        $this->expectException(RuntimeException::class);
        $service->setRole(2, \OpenPhonebook\Auth\Role::OrganizationAdmin, 1);
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
