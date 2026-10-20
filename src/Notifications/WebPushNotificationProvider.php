<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

use RuntimeException;

final class WebPushNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private readonly WebPushTransportInterface $transport
    ) {
    }

    public function name(): string
    {
        return 'web_push';
    }

    public function send(
        array $config,
        NotificationMessage $message
    ): void {
        $subscription = WebPushSubscriptionValidator::fromConfig($config);
        $result = $this->transport->send($subscription, $message);

        if ($result === WebPushTransportResult::Expired) {
            throw new ExpiredNotificationChannelException(
                'Web Push subscription has expired.'
            );
        }

        if ($result !== WebPushTransportResult::Sent) {
            throw new RuntimeException(
                'Unexpected Web Push transport result.'
            );
        }
    }
}
