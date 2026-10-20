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
        string $csrfToken,
        ?string $notice = null,
        ?string $error = null
    ): string {
        return NotificationManagementView::render(
            $this->translator,
            $this->notifications->channels($ownerUserId),
            $this->notifications->rules($ownerUserId),
            $this->notifications->devices($ownerUserId),
            $csrfToken,
            $notice,
            $error
        );
    }
}
