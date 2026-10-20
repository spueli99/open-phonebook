<?php

declare(strict_types=1);

namespace OpenPhonebook\Exports;

use JsonException;
use RuntimeException;

final class JsonPhonebookExporter implements PhonebookExporterInterface
{
    public function format(): string
    {
        return 'json';
    }

    public function export(array $entries): PhonebookExport
    {
        $contacts = [];

        foreach ($entries as $entry) {
            $numbers = [];

            foreach ($entry->phoneNumbers as $phoneNumber) {
                $numbers[] = [
                    'number' => $phoneNumber->number,
                    'label' => $phoneNumber->label,
                ];
            }

            $contacts[] = [
                'name' => $entry->displayName,
                'numbers' => $numbers,
            ];
        }

        try {
            $body = json_encode(
                ['version' => 1, 'contacts' => $contacts],
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Phonebook JSON encoding failed.',
                0,
                $exception
            );
        }

        return new PhonebookExport(
            $body . "\n",
            'application/json; charset=utf-8',
            'phonebook.json'
        );
    }
}
