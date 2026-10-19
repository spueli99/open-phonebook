<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Devices\DeviceAdministrationService;
use OpenPhonebook\Devices\DeviceCredentialCreationResult;
use OpenPhonebook\I18n\Translator;

final class DeviceManagementPage
{
    public function __construct(
        private readonly DeviceAdministrationService $devices,
        private readonly Translator $translator,
    ) {
    }

    public function render(
        int $ownerUserId,
        string $csrfToken,
        ?DeviceCredentialCreationResult $credential = null,
        ?string $notice = null,
        ?string $error = null
    ): string {
        $devices = $this->devices->allOwnedBy($ownerUserId);
        $assignments = [];
        $credentialStates = [];

        foreach ($devices as $device) {
            $assignments[$device->id] = $this->devices->addressBookIds(
                $ownerUserId,
                $device->id
            );
            $credentialStates[$device->id] =
                $this->devices->hasActiveCredential(
                    $ownerUserId,
                    $device->id
                );
        }

        return DeviceManagementView::render(
            $this->translator,
            $devices,
            $this->devices->accessibleAddressBooks($ownerUserId),
            $assignments,
            $credentialStates,
            $csrfToken,
            $credential,
            $notice,
            $error
        );
    }
}
