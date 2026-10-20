<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

interface WebPushTransportInterface
{
    public function send(
        WebPushSubscriptionData $subscription,
        NotificationMessage $message
    ): WebPushTransportResult;
}
