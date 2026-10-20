<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Notifications;

use OpenPhonebook\Calls\CallDirection;
use OpenPhonebook\Calls\CallEventResult;
use OpenPhonebook\Calls\CallSession;
use OpenPhonebook\Calls\CallSessionState;
use OpenPhonebook\Devices\AuthenticatedDevice;
use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Notifications\CallNotificationDispatcher;
use OpenPhonebook\Notifications\NotificationConfigCipher;
use OpenPhonebook\Notifications\NotificationProviderRegistry;
use OpenPhonebook\Notifications\NotificationTrigger;
use OpenPhonebook\Security\SecretCipher;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationChannelRepository;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationRuleRepository;
use OpenPhonebook\Tests\Support\Notifications\RecordingNotificationProvider;
use PHPUnit\Framework\TestCase;

final class CallNotificationDispatcherTest extends TestCase
{
    private const APP_KEY =
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testIncomingRuleSendsResolvedCaller(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $rules = new InMemoryNotificationRuleRepository();
        $cipher = new NotificationConfigCipher(
            new SecretCipher(self::APP_KEY)
        );
        $channelId = $channels->create(
            10,
            'Push',
            'recording',
            $cipher->encrypt(['target' => 'one'])
        );
        $rules->create(
            10,
            $channelId,
            1,
            NotificationTrigger::Incoming
        );
        $provider = new RecordingNotificationProvider('recording');
        $dispatcher = new CallNotificationDispatcher(
            $rules,
            $channels,
            new NotificationProviderRegistry([$provider]),
            $cipher
        );

        $dispatcher->dispatch(
            $this->device(),
            new CallEventResult(
                new CallSession(
                    1,
                    1,
                    'call-1',
                    CallDirection::Incoming,
                    CallSessionState::Ringing,
                    '100',
                    '+49123',
                    'Alice'
                ),
                true
            )
        );

        self::assertCount(1, $provider->sent);
        self::assertSame(
            'Incoming call from Alice',
            $provider->sent[0]['message']->body
        );
    }

    public function testInactiveChannelAndProviderFailureAreIsolated(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $rules = new InMemoryNotificationRuleRepository();
        $cipher = new NotificationConfigCipher(
            new SecretCipher(self::APP_KEY)
        );
        $failedId = $channels->create(
            10,
            'Failing',
            'fail',
            $cipher->encrypt(['target' => 'fail'])
        );
        $workingId = $channels->create(
            10,
            'Working',
            'ok',
            $cipher->encrypt(['target' => 'ok'])
        );
        $disabledId = $channels->create(
            10,
            'Disabled',
            'ok',
            $cipher->encrypt(['target' => 'disabled'])
        );
        $channels->setActive($disabledId, 10, false);

        foreach ([$failedId, $workingId, $disabledId] as $channelId) {
            $rules->create(
                10,
                $channelId,
                null,
                NotificationTrigger::Missed
            );
        }

        $failing = new RecordingNotificationProvider('fail', true);
        $working = new RecordingNotificationProvider('ok');
        $dispatcher = new CallNotificationDispatcher(
            $rules,
            $channels,
            new NotificationProviderRegistry([$failing, $working]),
            $cipher
        );

        $dispatcher->dispatch(
            $this->device(),
            new CallEventResult(
                new CallSession(
                    2,
                    1,
                    'call-2',
                    CallDirection::Incoming,
                    CallSessionState::Missed,
                    '100',
                    '+49123',
                    null
                ),
                false
            )
        );

        self::assertCount(1, $working->sent);
        self::assertSame(
            ['target' => 'ok'],
            $working->sent[0]['config']
        );
    }

    public function testNonMatchingStateDoesNotNotify(): void
    {
        $provider = new RecordingNotificationProvider('recording');
        $dispatcher = new CallNotificationDispatcher(
            new InMemoryNotificationRuleRepository(),
            new InMemoryNotificationChannelRepository(),
            new NotificationProviderRegistry([$provider]),
            new NotificationConfigCipher(
                new SecretCipher(self::APP_KEY)
            )
        );

        $dispatcher->dispatch(
            $this->device(),
            new CallEventResult(
                new CallSession(
                    3,
                    1,
                    'call-3',
                    CallDirection::Outgoing,
                    CallSessionState::Calling,
                    '100',
                    '+49211',
                    null
                ),
                true
            )
        );

        self::assertSame([], $provider->sent);
    }

    private function device(): AuthenticatedDevice
    {
        return new AuthenticatedDevice(
            1,
            10,
            DeviceCredentialScope::CallEventsWrite
        );
    }
}
