<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\I18n\Translator;
use OpenPhonebook\Notifications\NotificationAdministrationService;

final class NotificationManagementPage
{
    public function __construct(
        private readonly NotificationAdministrationService $notifications,
        private readonly Translator $translator,
    ) {
    }

    public function render(
        int $ownerUserId,
        string $timezone,
        string $csrfToken,
        ?string $webPushPublicKey = null,
        ?string $notice = null,
        ?string $error = null
    ): string {
        return NotificationManagementView::render(
            $this->translator,
            $this->notifications->channels($ownerUserId),
            $this->notifications->rules($ownerUserId),
            $this->notifications->devices($ownerUserId),
            $this->notifications->deliveryCounts($ownerUserId),
            $this->notifications->deliveryHistory($ownerUserId),
            $timezone,
            $csrfToken,
            $webPushPublicKey,
            $notice,
            $error
        );
    }
}
