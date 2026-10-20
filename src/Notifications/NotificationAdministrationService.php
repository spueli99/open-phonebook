<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use OpenPhonebook\Clock\ClockInterface;
use OpenPhonebook\Devices\DeviceRepositoryInterface;
use RuntimeException;

final class NotificationAdministrationService
{
    public function __construct(
        private readonly NotificationChannelRepositoryInterface $channels,
        private readonly NotificationRuleRepositoryInterface $rules,
        private readonly DeviceRepositoryInterface $devices,
        private readonly NotificationConfigCipher $configCipher,
        private readonly NotificationDeliveryRepositoryInterface $deliveries,
        private readonly NotificationMessageCipher $messageCipher,
        private readonly ClockInterface $clock,
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
     * @return array{
     *     pending: int,
     *     processing: int,
     *     sent: int,
     *     failed: int,
     *     cancelled: int
     * }
     */
    public function deliveryCounts(int $ownerUserId): array
    {
        return $this->deliveries->countsOwnedBy($ownerUserId);
    }

    public function deliveryHistory(
        int $ownerUserId,
        NotificationDeliveryHistoryFilter $filter,
        int $limit = 50
    ): NotificationDeliveryHistoryPage {
        return $this->deliveries->historyOwnedBy(
            $ownerUserId,
            $filter,
            $limit
        );
    }

    public function retryDelivery(
        int $ownerUserId,
        int $deliveryId
    ): void {
        $candidate = $this->deliveries->retryCandidateOwnedBy(
            $deliveryId,
            $ownerUserId
        );

        if ($candidate === null || $candidate->channelId === null) {
            throw new RuntimeException(
                'Notification delivery is not retryable.'
            );
        }

        $channel = $this->channels->findOwnedBy(
            $candidate->channelId,
            $ownerUserId
        );

        if ($channel === null || !$channel->isActive) {
            throw new RuntimeException(
                'Notification channel is not active.'
            );
        }

        $message = $this->messageCipher->decrypt(
            $candidate->encryptedPayload
        );
        $encryptedPayload = $this->messageCipher->encrypt($message);

        if (
            !$this->deliveries->retryFailedOwnedBy(
                $deliveryId,
                $ownerUserId,
                $message,
                $encryptedPayload,
                $this->clock->now()
            )
        ) {
            throw new RuntimeException(
                'Notification delivery could not be retried.'
            );
        }
    }

    public function testChannel(
        int $ownerUserId,
        int $channelId
    ): int {
        $channel = $this->channels->findOwnedBy(
            $channelId,
            $ownerUserId
        );

        if ($channel === null || !$channel->isActive) {
            throw new RuntimeException(
                'Notification channel is not active.'
            );
        }

        $message = new NotificationMessage(
            'Open Phonebook test notification',
            'This is a test notification from Open Phonebook.'
        );

        return $this->deliveries->enqueue(
            $ownerUserId,
            $channel->id,
            NotificationTrigger::Test,
            $message,
            $this->messageCipher->encrypt($message),
            $this->clock->now()
        );
    }

    public function registerWebPush(
        int $ownerUserId,
        string $endpoint,
        string $p256dh,
        string $auth,
        string $contentEncoding,
        string $browserId
    ): int {
        $subscription = WebPushSubscriptionValidator::validate(
            $endpoint,
            $p256dh,
            $auth,
            $contentEncoding,
            $browserId
        );
        $encrypted = $this->configCipher->encrypt(
            $subscription->toConfig()
        );

        foreach ($this->channels->allOwnedBy($ownerUserId) as $channel) {
            if ($channel->provider !== 'web_push') {
                continue;
            }

            try {
                $existing = $this->configCipher->decrypt(
                    $channel->encryptedConfig
                );
            } catch (\Throwable) {
                continue;
            }

            if (
                ($existing['browser_id'] ?? '') !== $browserId
                && ($existing['endpoint'] ?? '')
                    !== $subscription->endpoint
            ) {
                continue;
            }

            $target = $subscription->toConfig();

            if ($existing !== $target) {
                if (
                    !$this->channels->updateConfig(
                        $channel->id,
                        $ownerUserId,
                        $encrypted
                    )
                ) {
                    throw new RuntimeException(
                        'Web Push channel could not be updated.'
                    );
                }
            }

            if (!$channel->isActive) {
                $this->channels->setActive(
                    $channel->id,
                    $ownerUserId,
                    true
                );
            }

            return $channel->id;
        }

        return $this->channels->create(
            $ownerUserId,
            'Web Push',
            'web_push',
            $encrypted
        );
    }

    public function webPushActive(
        int $ownerUserId,
        string $browserId,
        string $endpoint
    ): bool {
        self::validateBrowserId($browserId);
        $endpoint = trim($endpoint);

        foreach ($this->channels->allOwnedBy($ownerUserId) as $channel) {
            if ($channel->provider !== 'web_push') {
                continue;
            }

            $config = $this->decryptWebPushConfig($channel);

            if (
                $config === null
                || !self::matchesWebPushBrowser(
                    $config,
                    $browserId,
                    $endpoint
                )
            ) {
                continue;
            }

            return $channel->isActive;
        }

        return false;
    }

    public function disableWebPush(
        int $ownerUserId,
        string $browserId,
        string $endpoint
    ): void {
        self::validateBrowserId($browserId);
        $endpoint = trim($endpoint);

        foreach ($this->channels->allOwnedBy($ownerUserId) as $channel) {
            if ($channel->provider !== 'web_push') {
                continue;
            }

            $config = $this->decryptWebPushConfig($channel);

            if (
                $config === null
                || !self::matchesWebPushBrowser(
                    $config,
                    $browserId,
                    $endpoint
                )
            ) {
                continue;
            }

            if ($channel->isActive) {
                $this->channels->setActive(
                    $channel->id,
                    $ownerUserId,
                    false
                );
            }

            return;
        }
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
        NotificationTrigger $trigger,
        ?string $quietStart = null,
        ?string $quietEnd = null
    ): int {
        if ($trigger === NotificationTrigger::Test) {
            throw new RuntimeException(
                'Test notifications cannot be used as rules.'
            );
        }

        QuietHoursPolicy::validateWindow($quietStart, $quietEnd);

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
            $trigger,
            $quietStart,
            $quietEnd
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
     * @return array<string, string>|null
     */
    private function decryptWebPushConfig(
        NotificationChannel $channel
    ): ?array {
        try {
            return $this->configCipher->decrypt(
                $channel->encryptedConfig
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, string> $config
     */
    private static function matchesWebPushBrowser(
        array $config,
        string $browserId,
        string $endpoint
    ): bool {
        return ($config['browser_id'] ?? '') === $browserId
            || (
                $endpoint !== ''
                && ($config['endpoint'] ?? '') === $endpoint
            );
    }

    private static function validateBrowserId(string $browserId): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $browserId) !== 1) {
            throw new RuntimeException(
                'Invalid Web Push browser identifier.'
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
            'web_push' => WebPushSubscriptionValidator::fromConfig(
                $config
            )->toConfig(),
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
