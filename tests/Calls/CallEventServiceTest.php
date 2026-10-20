<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Calls;

use OpenPhonebook\Calls\CallDirection;
use OpenPhonebook\Calls\CallEventService;
use OpenPhonebook\Calls\CallEventType;
use OpenPhonebook\Calls\CallerLookupService;
use OpenPhonebook\Calls\CallSessionState;
use OpenPhonebook\Calls\NormalizedCallEvent;
use OpenPhonebook\Contacts\AddressBookAccessService;
use OpenPhonebook\Devices\AuthenticatedDevice;
use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Tests\Support\Calls\InMemoryCallSessionRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookAccessRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryAddressBookRepository;
use OpenPhonebook\Tests\Support\Contacts\InMemoryContactRepository;
use OpenPhonebook\Tests\Support\Contacts\TestPhoneNumberNormalizer;
use OpenPhonebook\Tests\Support\Database\InMemoryTransactionManager;
use OpenPhonebook\Tests\Support\Devices\InMemoryDeviceAddressBookRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CallEventServiceTest extends TestCase
{
    public function testIncomingAnsweredCallCompletes(): void
    {
        [$service, $sessions] = $this->service();
        $device = $this->device();

        $ringing = $service->record(
            $device,
            new NormalizedCallEvent(
                'call-1',
                CallEventType::Ringing,
                CallDirection::Incoming,
                '100',
                '+491701234567'
            )
        );
        $connected = $service->record(
            $device,
            new NormalizedCallEvent(
                'call-1',
                CallEventType::Connected,
                null,
                null,
                null
            )
        );
        $completed = $service->record(
            $device,
            new NormalizedCallEvent(
                'call-1',
                CallEventType::Idle,
                null,
                null,
                null
            )
        );

        self::assertTrue($ringing->created);
        self::assertSame(CallSessionState::Connected, $connected->session->state);
        self::assertSame(CallSessionState::Completed, $completed->session->state);
        self::assertCount(3, $sessions->events);
    }

    public function testUnansweredIncomingCallBecomesMissed(): void
    {
        [$service] = $this->service();
        $device = $this->device();

        $service->record(
            $device,
            new NormalizedCallEvent(
                'call-2',
                CallEventType::Ringing,
                CallDirection::Incoming,
                null,
                '+49123'
            )
        );
        $result = $service->record(
            $device,
            new NormalizedCallEvent(
                'call-2',
                CallEventType::Idle,
                null,
                null,
                null
            )
        );

        self::assertSame(CallSessionState::Missed, $result->session->state);
    }

    public function testOutgoingCancellationBecomesCancelled(): void
    {
        [$service] = $this->service();
        $device = $this->device();

        $service->record(
            $device,
            new NormalizedCallEvent(
                'call-3',
                CallEventType::Calling,
                CallDirection::Outgoing,
                '100',
                '+49211123'
            )
        );
        $result = $service->record(
            $device,
            new NormalizedCallEvent(
                'call-3',
                CallEventType::Idle,
                null,
                null,
                null
            )
        );

        self::assertSame(CallSessionState::Cancelled, $result->session->state);
    }

    public function testExternalCallIdMayBeReusedAfterTerminalState(): void
    {
        [$service] = $this->service();
        $device = $this->device();

        $service->record(
            $device,
            new NormalizedCallEvent(
                'reused',
                CallEventType::Ringing,
                CallDirection::Incoming,
                null,
                '+49123'
            )
        );
        $service->record(
            $device,
            new NormalizedCallEvent(
                'reused',
                CallEventType::Idle,
                null,
                null,
                null
            )
        );

        $second = $service->record(
            $device,
            new NormalizedCallEvent(
                'reused',
                CallEventType::Calling,
                CallDirection::Outgoing,
                '100',
                '+49211'
            )
        );

        self::assertTrue($second->created);
        self::assertSame(
            CallSessionState::Calling,
            $second->session->state
        );
        self::assertSame(
            CallDirection::Outgoing,
            $second->session->direction
        );
    }

    public function testFollowUpWithoutSessionIsRejected(): void
    {
        [$service] = $this->service();

        $this->expectException(RuntimeException::class);
        $service->record(
            $this->device(),
            new NormalizedCallEvent(
                'missing',
                CallEventType::Connected,
                null,
                null,
                null
            )
        );
    }

    /**
     * @return array{CallEventService, InMemoryCallSessionRepository}
     */
    private function service(): array
    {
        $books = new InMemoryAddressBookRepository();
        $access = new InMemoryAddressBookAccessRepository($books);
        $lookup = new CallerLookupService(
            new InMemoryDeviceAddressBookRepository(),
            new AddressBookAccessService($access),
            new InMemoryContactRepository(),
            new TestPhoneNumberNormalizer()
        );
        $sessions = new InMemoryCallSessionRepository();

        return [
            new CallEventService(
                $sessions,
                $lookup,
                new InMemoryTransactionManager()
            ),
            $sessions,
        ];
    }

    private function device(): AuthenticatedDevice
    {
        return new AuthenticatedDevice(
            1,
            1,
            10,
            DeviceCredentialScope::CallEventsWrite
        );
    }
}
