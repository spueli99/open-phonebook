<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\UserManagement;

use DateInterval;
use OpenPhonebook\Auth\Role;
use OpenPhonebook\Tests\Support\Clock\FrozenClock;
use OpenPhonebook\Tests\Support\UserManagement\InMemoryInvitationRepository;
use OpenPhonebook\Tests\Support\UserManagement\InMemoryUserAdministrationRepository;
use OpenPhonebook\UserManagement\InvitationService;
use OpenPhonebook\UserManagement\UserSummary;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationServiceTest extends TestCase
{
    public function testCreatesResolvableMemberInvitation(): void
    {
        $invitations = new InMemoryInvitationRepository();
        $users = new InMemoryUserAdministrationRepository([]);
        $clock = new FrozenClock('2026-10-19 12:00:00');
        $service = new InvitationService(
            $invitations,
            $users,
            $clock,
            str_repeat('a', 64),
        );

        $result = $service->create(
            'New.User@example.com',
            Role::Member,
            1
        );

        self::assertSame('new.user@example.com', $result->email);
        self::assertSame(Role::Member, $result->role);
        self::assertSame(64, strlen($result->token));

        $resolved = $service->resolve($result->token);
        self::assertNotNull($resolved);
        self::assertSame('new.user@example.com', $resolved->email);
    }

    public function testInvitationExpiresAfterThreeDays(): void
    {
        $invitations = new InMemoryInvitationRepository();
        $clock = new FrozenClock('2026-10-19 12:00:00');
        $service = new InvitationService(
            $invitations,
            new InMemoryUserAdministrationRepository([]),
            $clock,
            str_repeat('a', 64),
        );

        $result = $service->create(
            'user@example.com',
            Role::Member,
            1
        );

        $clock->advance(new DateInterval('P4D'));

        self::assertNull($service->resolve($result->token));
    }

    public function testExistingUserCannotBeInvited(): void
    {
        $existing = new UserSummary(
            1,
            'user@example.com',
            'User',
            'member',
            true,
            false,
            '2026-10-19 12:00:00',
        );
        $service = new InvitationService(
            new InMemoryInvitationRepository(),
            new InMemoryUserAdministrationRepository([1 => $existing]),
            new FrozenClock('2026-10-19 12:00:00'),
            str_repeat('a', 64),
        );

        $this->expectException(RuntimeException::class);

        $service->create(
            'user@example.com',
            Role::Member,
            1
        );
    }

    public function testDuplicateActiveInvitationIsRejected(): void
    {
        $service = new InvitationService(
            new InMemoryInvitationRepository(),
            new InMemoryUserAdministrationRepository([]),
            new FrozenClock('2026-10-19 12:00:00'),
            str_repeat('a', 64),
        );

        $service->create('user@example.com', Role::Member, 1);

        $this->expectException(RuntimeException::class);
        $service->create('user@example.com', Role::Member, 1);
    }

    public function testOrganizationAdminInvitationRequiresOrganizationContext(): void
    {
        $service = new InvitationService(
            new InMemoryInvitationRepository(),
            new InMemoryUserAdministrationRepository([]),
            new FrozenClock('2026-10-19 12:00:00'),
            str_repeat('a', 64),
        );

        $this->expectException(RuntimeException::class);

        $service->create(
            'user@example.com',
            Role::OrganizationAdmin,
            1
        );
    }
}
