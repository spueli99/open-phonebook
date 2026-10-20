<?php

declare(strict_types=1);

namespace OpenPhonebook\Exports;

final class CsvPhonebookExporter implements PhonebookExporterInterface
{
    public function format(): string
    {
        return 'csv';
    }

    public function export(array $entries): PhonebookExport
    {
        $rows = [
            ['display_name', 'number', 'label'],
        ];

        foreach ($entries as $entry) {
            foreach ($entry->phoneNumbers as $phoneNumber) {
                $rows[] = [
                    $this->spreadsheetSafe($entry->displayName),
                    $this->spreadsheetSafe($phoneNumber->number),
                    $this->spreadsheetSafe($phoneNumber->label ?? ''),
                ];
            }
        }

        $lines = [];

        foreach ($rows as $row) {
            $lines[] = implode(
                ',',
                array_map(
                    fn (string $value): string => $this->cell($value),
                    $row
                )
            );
        }

        return new PhonebookExport(
            implode("\r\n", $lines) . "\r\n",
            'text/csv; charset=utf-8',
            'phonebook.csv'
        );
    }

    private function cell(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function spreadsheetSafe(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $first = $value[0];

        if (
            $first === '='
            || $first === '+'
            || $first === '-'
            || $first === '@'
        ) {
            return "'" . $value;
        }

        return $value;
    }
}
