<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

use OpenPhonebook\Contacts\Contact;
use OpenPhonebook\Contacts\ContactPhoneNumberInput;
use OpenPhonebook\Contacts\PhoneNumberNormalizerInterface;

final class ImportContactMerger
{
    public function __construct(
        private readonly PhoneNumberNormalizerInterface $normalizer
    ) {
    }

    /**
     * @return array{
     *     display_name: string,
     *     given_name: ?string,
     *     family_name: ?string,
     *     organization_name: ?string,
     *     notes: ?string,
     *     phone_numbers: list<ContactPhoneNumberInput>
     * }
     */
    public function merge(
        Contact $existing,
        ImportPreviewContact $incoming
    ): array {
        $phoneNumbers = [];
        $knownNumbers = [];

        foreach ($existing->phoneNumbers as $phoneNumber) {
            $normalized = $phoneNumber->normalizedE164
                ?? $this->normalizer->normalize(
                    $phoneNumber->rawNumber
                );
            $key = $this->phoneKey(
                $phoneNumber->rawNumber,
                $normalized
            );
            $knownNumbers[$key] = true;
            $phoneNumbers[] = new ContactPhoneNumberInput(
                $phoneNumber->label,
                $phoneNumber->rawNumber,
                $phoneNumber->normalizedE164,
            );
        }

        foreach ($incoming->phoneNumbers as $phoneNumber) {
            $normalized = $this->normalizer->normalize(
                $phoneNumber->rawNumber
            );
            $key = $this->phoneKey(
                $phoneNumber->rawNumber,
                $normalized
            );

            if (isset($knownNumbers[$key])) {
                continue;
            }

            $knownNumbers[$key] = true;
            $phoneNumbers[] = new ContactPhoneNumberInput(
                $phoneNumber->label,
                $phoneNumber->rawNumber,
                $normalized,
            );
        }

        return [
            'display_name' => $existing->displayName,
            'given_name' => $existing->givenName ?? $incoming->givenName,
            'family_name' => $existing->familyName ?? $incoming->familyName,
            'organization_name' => $existing->organizationName
                ?? $incoming->organizationName,
            'notes' => $existing->notes ?? $incoming->notes,
            'phone_numbers' => $phoneNumbers,
        ];
    }

    private function phoneKey(
        string $rawNumber,
        ?string $normalizedE164
    ): string {
        if ($normalizedE164 !== null) {
            return 'e164:' . $normalizedE164;
        }

        return 'raw:' . mb_strtolower(trim($rawNumber));
    }
}
