<?php

declare(strict_types=1);

namespace OpenPhonebook\Calls;

use JsonException;
use RuntimeException;

final class JsonCallEventParser
{
    public function parse(string $body): NormalizedCallEvent
    {
        if ($body === '' || strlen($body) > 16_384) {
            throw new RuntimeException('Invalid call event body.');
        }

        try {
            $data = json_decode(
                $body,
                true,
                16,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Invalid call event JSON.',
                0,
                $exception
            );
        }

        if (!is_array($data)) {
            throw new RuntimeException('Invalid call event payload.');
        }

        $type = $this->enumString($data, 'event');
        $direction = $data['direction'] ?? null;

        return new NormalizedCallEvent(
            $this->requiredString($data, 'call_id', 191),
            CallEventType::tryFrom($type)
                ?? throw new RuntimeException('Invalid call event type.'),
            $direction === null
                ? null
                : CallDirection::tryFrom(
                    $this->scalarString($direction)
                ),
            $this->optionalString($data, 'local_number', 120),
            $this->optionalString($data, 'remote_number', 120),
        );
    }

    /**
     * @param array<mixed> $data
     */
    private function enumString(array $data, string $key): string
    {
        return $this->requiredString($data, $key, 32);
    }

    /**
     * @param array<mixed> $data
     */
    private function requiredString(
        array $data,
        string $key,
        int $maxLength
    ): string {
        $value = $data[$key] ?? null;

        if (!is_string($value)) {
            throw new RuntimeException('Missing call event field.');
        }

        $value = trim($value);

        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new RuntimeException('Invalid call event field.');
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function optionalString(
        array $data,
        string $key,
        int $maxLength
    ): ?string {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new RuntimeException('Invalid call event field.');
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            throw new RuntimeException('Invalid call event field.');
        }

        return $value;
    }

    private function scalarString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new RuntimeException('Invalid call direction.');
        }

        $value = trim($value);

        if ($value === '') {
            throw new RuntimeException('Invalid call direction.');
        }

        return $value;
    }
}
