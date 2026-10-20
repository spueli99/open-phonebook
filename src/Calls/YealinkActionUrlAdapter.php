<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

use RuntimeException;

final class YealinkActionUrlAdapter
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function normalize(
        string $event,
        array $parameters
    ): NormalizedCallEvent {
        [$type, $direction] = match ($event) {
            'incoming_call' => [
                CallEventType::Ringing,
                CallDirection::Incoming,
            ],
            'outgoing_call' => [
                CallEventType::Calling,
                CallDirection::Outgoing,
            ],
            'call_established' => [
                CallEventType::Connected,
                null,
            ],
            'on_hook',
            'missed_call',
            'cancel_callout',
            'remote_busy',
            'call_remote_canceled' => [
                CallEventType::Idle,
                null,
            ],
            default => throw new RuntimeException(
                'Unsupported Yealink action event.'
            ),
        };

        return new NormalizedCallEvent(
            $this->required($parameters, 'call_id', 191),
            $type,
            $direction,
            $this->phoneValue($parameters, 'local'),
            $this->phoneValue($parameters, 'remote'),
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function phoneValue(
        array $parameters,
        string $key
    ): ?string {
        $value = $this->optional($parameters, $key, 120);

        if ($value === null) {
            return null;
        }

        if (
            str_starts_with(strtolower($value), 'sip:')
            || str_starts_with(strtolower($value), 'sips:')
        ) {
            $value = substr($value, strpos($value, ':') + 1);
            $at = strpos($value, '@');

            if ($at !== false) {
                $value = substr($value, 0, $at);
            }
        }

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function required(
        array $parameters,
        string $key,
        int $maxLength
    ): string {
        return $this->optional($parameters, $key, $maxLength)
            ?? throw new RuntimeException(
                'Required Yealink value is missing.'
            );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function optional(
        array $parameters,
        string $key,
        int $maxLength
    ): ?string {
        $value = $parameters[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new RuntimeException('Invalid Yealink value.');
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            throw new RuntimeException('Yealink value is too long.');
        }

        return $value;
    }
}
