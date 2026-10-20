<?php

declare(strict_types=1);

namespace OpenPhonebook\Web;

use OpenPhonebook\Devices\DeviceAdministrationService;
use OpenPhonebook\Devices\DeviceCredentialCreationResult;
use OpenPhonebook\Devices\DeviceCredentialScope;
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
        string $timezone,
        string $csrfToken,
        ?DeviceCredentialCreationResult $credential = null,
        ?string $notice = null,
        ?string $error = null
    ): string {
        $devices = $this->devices->allOwnedBy($ownerUserId);
        $assignments = [];
        $phonebookCredentialStates = [];
        $callEventCredentialStates = [];
        $phonebookLastUsedAt = [];
        $callEventLastUsedAt = [];

        foreach ($devices as $device) {
            $assignments[$device->id] = $this->devices->addressBookIds(
                $ownerUserId,
                $device->id
            );
            $phonebookCredentialStates[$device->id] =
                $this->devices->hasActiveCredential(
                    $ownerUserId,
                    $device->id,
                    DeviceCredentialScope::PhonebookRead
                );
            $callEventCredentialStates[$device->id] =
                $this->devices->hasActiveCredential(
                    $ownerUserId,
                    $device->id,
                    DeviceCredentialScope::CallEventsWrite
                );
            $phonebookLastUsedAt[$device->id] =
                $this->devices->activeCredentialLastUsedAt(
                    $ownerUserId,
                    $device->id,
                    DeviceCredentialScope::PhonebookRead
                );
            $callEventLastUsedAt[$device->id] =
                $this->devices->activeCredentialLastUsedAt(
                    $ownerUserId,
                    $device->id,
                    DeviceCredentialScope::CallEventsWrite
                );
        }

        return DeviceManagementView::render(
            $this->translator,
            $devices,
            $this->devices->accessibleAddressBooks($ownerUserId),
            $assignments,
            $phonebookCredentialStates,
            $callEventCredentialStates,
            $phonebookLastUsedAt,
            $callEventLastUsedAt,
            $timezone,
            $csrfToken,
            $credential,
            $notice,
            $error
        );
    }
}
