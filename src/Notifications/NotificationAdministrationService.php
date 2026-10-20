<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use OpenPhonebook\Devices\DeviceRepositoryInterface;
use RuntimeException;

final class NotificationAdministrationService
{
    public function __construct(
        private readonly NotificationChannelRepositoryInterface $channels,
        private readonly NotificationRuleRepositoryInterface $rules,
        private readonly DeviceRepositoryInterface $devices,
        private readonly NotificationConfigCipher $configCipher,
    ) {
    }

    /**
     * @return list<\OpenPhonebook\Devices\Device>
     */
    public function devices(int $ownerUserId): array
    {
        return $this->devices->allOwnedBy($ownerUserId);
    }

    /**
     * @return list<NotificationChannel>
     */
    public function channels(int $ownerUserId): array
    {
        return $this->channels->allOwnedBy($ownerUserId);
    }

    /**
     * @return list<NotificationRule>
     */
    public function rules(int $ownerUserId): array
    {
        return $this->rules->allOwnedBy($ownerUserId);
    }

    /**
     * @param array<string, string> $config
     */
    public function createChannel(
        int $ownerUserId,
        string $name,
        string $provider,
        array $config
    ): int {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 120) {
            throw new RuntimeException(
                'Invalid notification channel name.'
            );
        }

        $config = $this->validateConfig($provider, $config);

        return $this->channels->create(
            $ownerUserId,
            $name,
            $provider,
            $this->configCipher->encrypt($config)
        );
    }

    public function setChannelActive(
        int $ownerUserId,
        int $channelId,
        bool $active
    ): void {
        if (
            !$this->channels->setActive(
                $channelId,
                $ownerUserId,
                $active
            )
        ) {
            throw new RuntimeException(
                'Notification channel not found.'
            );
        }
    }

    public function deleteChannel(
        int $ownerUserId,
        int $channelId
    ): void {
        if (!$this->channels->delete($channelId, $ownerUserId)) {
            throw new RuntimeException(
                'Notification channel not found.'
            );
        }
    }

    public function createRule(
        int $ownerUserId,
        int $channelId,
        ?int $deviceId,
        NotificationTrigger $trigger
    ): int {
        $channel = $this->channels->findOwnedBy(
            $channelId,
            $ownerUserId
        );

        if ($channel === null) {
            throw new RuntimeException(
                'Notification channel not found.'
            );
        }

        if (
            $deviceId !== null
            && $this->devices->findOwnedBy(
                $deviceId,
                $ownerUserId
            ) === null
        ) {
            throw new RuntimeException('Device not found.');
        }

        if (
            $this->rules->exists(
                $ownerUserId,
                $channelId,
                $deviceId,
                $trigger
            )
        ) {
            throw new RuntimeException(
                'Notification rule already exists.'
            );
        }

        return $this->rules->create(
            $ownerUserId,
            $channelId,
            $deviceId,
            $trigger
        );
    }

    public function deleteRule(
        int $ownerUserId,
        int $ruleId
    ): void {
        if (!$this->rules->delete($ruleId, $ownerUserId)) {
            throw new RuntimeException(
                'Notification rule not found.'
            );
        }
    }

    /**
     * @param array<string, string> $config
     * @return array<string, string>
     */
    private function validateConfig(
        string $provider,
        array $config
    ): array {
        return match ($provider) {
            'pushover' => $this->validatePushover($config),
            'email' => $this->validateEmail($config),
            default => throw new RuntimeException(
                'Unsupported notification provider.'
            ),
        };
    }

    /**
     * @param array<string, string> $config
     * @return array<string, string>
     */
    private function validatePushover(array $config): array
    {
        $token = trim($config['token'] ?? '');
        $user = trim($config['user'] ?? '');

        if (
            preg_match('/^[A-Za-z0-9]{30}$/', $token) !== 1
            || preg_match('/^[A-Za-z0-9]{30}$/', $user) !== 1
        ) {
            throw new RuntimeException(
                'Invalid Pushover configuration.'
            );
        }

        return ['token' => $token, 'user' => $user];
    }

    /**
     * @param array<string, string> $config
     * @return array<string, string>
     */
    private function validateEmail(array $config): array
    {
        $recipient = trim($config['recipient'] ?? '');
        $from = trim($config['from'] ?? '');

        if (
            !filter_var($recipient, FILTER_VALIDATE_EMAIL)
            || !filter_var($from, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException(
                'Invalid email notification configuration.'
            );
        }

        return [
            'recipient' => $recipient,
            'from' => $from,
        ];
    }
}
