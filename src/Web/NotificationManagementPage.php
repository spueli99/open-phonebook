<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\I18n\Translator;
use OpenPhonebook\Notifications\NotificationAdministrationService;
use OpenPhonebook\Notifications\NotificationDeliveryHistoryFilter;

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
        ?NotificationDeliveryHistoryFilter $historyFilter = null,
        ?string $notice = null,
        ?string $error = null
    ): string {
        $historyFilter ??= new NotificationDeliveryHistoryFilter();
        $channels = $this->notifications->channels($ownerUserId);

        return NotificationManagementView::render(
            $this->translator,
            $channels,
            $this->notifications->rules($ownerUserId),
            $this->notifications->devices($ownerUserId),
            $this->notifications->deliveryCounts($ownerUserId),
            $this->notifications->deliveryHistory(
                $ownerUserId,
                $historyFilter
            ),
            $historyFilter,
            $timezone,
            $csrfToken,
            $webPushPublicKey,
            $notice,
            $error
        );
    }
}
