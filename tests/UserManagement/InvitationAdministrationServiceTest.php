<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\UserManagement;

use DateInterval;
use OpenPhonebook\Audit\AuditEventType;
use OpenPhonebook\Auth\Role;
use OpenPhonebook\Tests\Support\Audit\RecordingAuditEventWriter;
use OpenPhonebook\Tests\Support\Clock\FrozenClock;
use OpenPhonebook\Tests\Support\UserManagement\InMemoryInvitationRepository;
use OpenPhonebook\UserManagement\InvitationAdministrationService;
use OpenPhonebook\UserManagement\InvitationService;
use OpenPhonebook\Tests\Support\UserManagement\InMemoryUserAdministrationRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvitationAdministrationServiceTest extends TestCase
{
    public function testListsOnlyUsableInvitations(): void
    {
        $repository = new InMemoryInvitationRepository();
        $clock = new FrozenClock('2026-10-19 12:00:00');
        $creation = new InvitationService(
            $repository,
            new InMemoryUserAdministrationRepository([]),
            $clock,
            str_repeat('a', 64),
        );
        $creation->create('user@example.com', Role::Member, 1);

        $service = new InvitationAdministrationService(
            $repository,
            $clock,
            str_repeat('a', 64),
        );

        self::assertCount(1, $service->allUsable());

        $clock->advance(new DateInterval('P4D'));

        self::assertSame([], $service->allUsable());
    }

    public function testRevokesUsableInvitation(): void
    {
        $repository = new InMemoryInvitationRepository();
        $clock = new FrozenClock('2026-10-19 12:00:00');
        $creation = new InvitationService(
            $repository,
            new InMemoryUserAdministrationRepository([]),
            $clock,
            str_repeat('a', 64),
        );
        $created = $creation->create('user@example.com', Role::Member, 1);

        $audit = new RecordingAuditEventWriter();
        $service = new InvitationAdministrationService(
            $repository,
            $clock,
            str_repeat('a', 64),
            $audit,
        );
        $service->revoke($created->id, 1);

        self::assertSame([], $service->allUsable());
        self::assertCount(1, $audit->events);
        self::assertSame(
            AuditEventType::InvitationRevoked,
            $audit->events[0]['type']
        );
        self::assertNull($creation->resolve($created->token));
    }

    public function testRenewReplacesTokenAndExtendsExpiry(): void
    {
        $repository = new InMemoryInvitationRepository();
        $clock = new FrozenClock('2026-10-19 12:00:00');
        $creation = new InvitationService(
            $repository,
            new InMemoryUserAdministrationRepository([]),
            $clock,
            str_repeat('a', 64),
        );
        $created = $creation->create('user@example.com', Role::Member, 1);
        $clock->advance(new DateInterval('P1D'));

        $audit = new RecordingAuditEventWriter();
        $service = new InvitationAdministrationService(
            $repository,
            $clock,
            str_repeat('a', 64),
            $audit,
        );
        $renewed = $service->renew($created->id, 2);

        self::assertNotSame($created->token, $renewed->token);
        self::assertCount(1, $audit->events);
        self::assertSame(
            AuditEventType::InvitationRenewed,
            $audit->events[0]['type']
        );
        self::assertNull($creation->resolve($created->token));
        self::assertNotNull($creation->resolve($renewed->token));
        self::assertSame(
            '2026-10-23 12:00:00',
            $renewed->expiresAt->format('Y-m-d H:i:s')
        );
    }

    public function testExpiredInvitationCannotBeRenewed(): void
    {
        $repository = new InMemoryInvitationRepository();
        $clock = new FrozenClock('2026-10-19 12:00:00');
        $creation = new InvitationService(
            $repository,
            new InMemoryUserAdministrationRepository([]),
            $clock,
            str_repeat('a', 64),
        );
        $created = $creation->create('user@example.com', Role::Member, 1);
        $clock->advance(new DateInterval('P4D'));

        $service = new InvitationAdministrationService(
            $repository,
            $clock,
            str_repeat('a', 64),
        );

        $this->expectException(RuntimeException::class);
        $service->renew($created->id, 1);
    }
}
