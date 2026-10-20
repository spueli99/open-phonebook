<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Notifications;

use OpenPhonebook\Notifications\NotificationMessage;
use OpenPhonebook\Notifications\WebPushSubscriptionData;
use OpenPhonebook\Notifications\WebPushTransportInterface;
use OpenPhonebook\Notifications\WebPushTransportResult;

final class RecordingWebPushTransport implements WebPushTransportInterface
{
    /** @var list<array{subscription: WebPushSubscriptionData, message: NotificationMessage}> */
    public array $sent = [];

    public function __construct(
        private readonly WebPushTransportResult $result =
            WebPushTransportResult::Sent
    ) {
    }

    public function send(
        WebPushSubscriptionData $subscription,
        NotificationMessage $message
    ): WebPushTransportResult {
        $this->sent[] = [
            'subscription' => $subscription,
            'message' => $message,
        ];

        return $this->result;
    }
}
