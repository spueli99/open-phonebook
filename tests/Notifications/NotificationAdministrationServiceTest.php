<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Notifications;

use OpenPhonebook\Notifications\NotificationAdministrationService;
use OpenPhonebook\Notifications\NotificationConfigCipher;
use OpenPhonebook\Notifications\NotificationTrigger;
use OpenPhonebook\Security\SecretCipher;
use OpenPhonebook\Tests\Support\Devices\InMemoryDeviceRepository;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationChannelRepository;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationDeliveryRepository;
use OpenPhonebook\Tests\Support\Notifications\InMemoryNotificationRuleRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NotificationAdministrationServiceTest extends TestCase
{
    private const APP_KEY =
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testChannelConfigIsEncryptedAndRuleRequiresOwnedDevice(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $rules = new InMemoryNotificationRuleRepository();
        $devices = new InMemoryDeviceRepository();
        $devices->create(10, 'Phone', null, null);
        $cipher = new NotificationConfigCipher(
            new SecretCipher(self::APP_KEY)
        );
        $service = new NotificationAdministrationService(
            $channels,
            $rules,
            $devices,
            $cipher,
            new InMemoryNotificationDeliveryRepository()
        );

        $channelId = $service->createChannel(
            10,
            'Push',
            'pushover',
            [
                'token' => str_repeat('A', 30),
                'user' => str_repeat('B', 30),
            ]
        );
        $channel = $channels->findOwnedBy($channelId, 10);

        self::assertNotNull($channel);
        self::assertStringNotContainsString(
            str_repeat('A', 30),
            $channel->encryptedConfig
        );
        self::assertSame(
            [
                'token' => str_repeat('A', 30),
                'user' => str_repeat('B', 30),
            ],
            $cipher->decrypt($channel->encryptedConfig)
        );

        $service->createRule(
            10,
            $channelId,
            1,
            NotificationTrigger::Missed,
            '22:00',
            '07:00'
        );

        self::assertCount(1, $service->rules(10));
        self::assertSame('22:00', $service->rules(10)[0]->quietStart);
        self::assertSame('07:00', $service->rules(10)[0]->quietEnd);

        try {
            $service->createRule(
                10,
                $channelId,
                1,
                NotificationTrigger::Missed,
                '22:00',
                '07:00'
            );
            self::fail('Duplicate notification rule was accepted.');
        } catch (RuntimeException) {
            self::assertCount(1, $service->rules(10));
        }

        $this->expectException(RuntimeException::class);
        $service->createRule(
            10,
            $channelId,
            999,
            NotificationTrigger::Incoming
        );
    }

    public function testInvalidQuietHoursAreRejected(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $devices = new InMemoryDeviceRepository();
        $service = new NotificationAdministrationService(
            $channels,
            new InMemoryNotificationRuleRepository(),
            $devices,
            new NotificationConfigCipher(
                new SecretCipher(self::APP_KEY)
            ),
            new InMemoryNotificationDeliveryRepository()
        );
        $channelId = $service->createChannel(
            10,
            'Mail',
            'email',
            [
                'recipient' => 'user@example.test',
                'from' => 'phonebook@example.test',
            ]
        );

        $this->expectException(RuntimeException::class);
        $service->createRule(
            10,
            $channelId,
            null,
            NotificationTrigger::Incoming,
            '22:00',
            null
        );
    }

    public function testOtherUsersCannotUseChannel(): void
    {
        $channels = new InMemoryNotificationChannelRepository();
        $service = new NotificationAdministrationService(
            $channels,
            new InMemoryNotificationRuleRepository(),
            new InMemoryDeviceRepository(),
            new NotificationConfigCipher(
                new SecretCipher(self::APP_KEY)
            ),
            new InMemoryNotificationDeliveryRepository()
        );
        $channelId = $service->createChannel(
            10,
            'Mail',
            'email',
            [
                'recipient' => 'user@example.test',
                'from' => 'phonebook@example.test',
            ]
        );

        $this->expectException(RuntimeException::class);
        $service->createRule(
            20,
            $channelId,
            null,
            NotificationTrigger::Missed
        );
    }
}
