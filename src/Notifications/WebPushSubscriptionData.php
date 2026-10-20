<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

final readonly class WebPushSubscriptionData
{
    public function __construct(
        public string $endpoint,
        public string $p256dh,
        public string $auth,
        public string $contentEncoding,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toConfig(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'p256dh' => $this->p256dh,
            'auth' => $this->auth,
            'content_encoding' => $this->contentEncoding,
        ];
    }
}
