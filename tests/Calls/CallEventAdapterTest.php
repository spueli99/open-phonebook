<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Calls;

use OpenPhonebook\Calls\CallDirection;
use OpenPhonebook\Calls\CallEventType;
use OpenPhonebook\Calls\JsonCallEventParser;
use OpenPhonebook\Calls\YealinkActionUrlAdapter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CallEventAdapterTest extends TestCase
{
    public function testJsonParserNormalizesIncomingEvent(): void
    {
        $event = (new JsonCallEventParser())->parse(
            '{"event":"ringing","call_id":"abc","direction":"incoming",'
            . '"local_number":"100","remote_number":"+49123"}'
        );

        self::assertSame(CallEventType::Ringing, $event->type);
        self::assertSame(CallDirection::Incoming, $event->direction);
        self::assertSame('abc', $event->externalCallId);
    }

    public function testYealinkAdapterMapsDocumentedEvents(): void
    {
        $adapter = new YealinkActionUrlAdapter();
        $incoming = $adapter->normalize(
            'incoming_call',
            [
                'call_id' => '42',
                'local' => 'sip:100@example.test',
                'remote' => 'sip:+49123@example.test',
            ]
        );
        $connected = $adapter->normalize(
            'call_established',
            ['call_id' => '42']
        );

        self::assertSame(CallEventType::Ringing, $incoming->type);
        self::assertSame(CallDirection::Incoming, $incoming->direction);
        self::assertSame(CallEventType::Connected, $connected->type);
        self::assertNull($connected->direction);
    }

    public function testYealinkAdapterRejectsUnknownEvents(): void
    {
        $this->expectException(RuntimeException::class);

        (new YealinkActionUrlAdapter())->normalize(
            'setup_completed',
            ['call_id' => '42']
        );
    }
}
