<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

use RuntimeException;

final class CsvContactImportParser implements ContactImportParserInterface
{
    public const MAX_BYTES = 2_000_000;
    public const MAX_ROWS = 2_000;
    public const MAX_FIELD_LENGTH = 10_000;

    /**
     * @var list<string>
     */
    private const REQUIRED_COLUMNS = ['display_name'];

    /**
     * @var list<string>
     */
    private const OPTIONAL_COLUMNS = [
        'given_name',
        'family_name',
        'organization_name',
        'notes',
        'phone_1',
        'phone_1_label',
        'phone_2',
        'phone_2_label',
        'phone_3',
        'phone_3_label',
    ];

    public function parse(string $contents): ImportPreviewResult
    {
        if ($contents === '') {
            throw new RuntimeException('Import file is empty.');
        }

        if (strlen($contents) > self::MAX_BYTES) {
            throw new RuntimeException('Import file is too large.');
        }

        if (str_contains($contents, "\0")) {
            throw new RuntimeException('Import file contains invalid binary data.');
        }

        if (!mb_check_encoding($contents, 'UTF-8')) {
            throw new RuntimeException('Import file must be valid UTF-8.');
        }

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Unable to open import parser stream.');
        }

        fwrite($stream, $contents);
        rewind($stream);

        try {
            $header = fgetcsv($stream);

            if ($header === false) {
                throw new RuntimeException('Import file has no header row.');
            }

            $columns = $this->normalizeHeader($header);
            $this->validateColumns($columns);

            $contacts = [];
            $warnings = [];
            $rowNumber = 1;

            while (($row = fgetcsv($stream)) !== false) {
                $rowNumber++;

                if ($rowNumber > self::MAX_ROWS + 1) {
                    throw new RuntimeException('Import file has too many rows.');
                }

                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $mapped = $this->mapRow($columns, $row, $rowNumber);
                $contact = $this->contactFromMappedRow(
                    $mapped,
                    $rowNumber,
                    $warnings
                );

                if ($contact !== null) {
                    $contacts[] = $contact;
                }
            }
        } finally {
            fclose($stream);
        }

        return new ImportPreviewResult($contacts, $warnings);
    }

    /**
     * @param list<string|null> $header
     * @return list<string>
     */
    private function normalizeHeader(array $header): array
    {
        $columns = [];

        foreach ($header as $column) {
            if (!is_string($column)) {
                throw new RuntimeException('Invalid CSV header.');
            }

            $column = trim($column);

            if (str_starts_with($column, "\xEF\xBB\xBF")) {
                $column = substr($column, 3);
            }

            $columns[] = strtolower($column);
        }

        return $columns;
    }

    /**
     * @param list<string> $columns
     */
    private function validateColumns(array $columns): void
    {
        if (count($columns) !== count(array_unique($columns))) {
            throw new RuntimeException('CSV header contains duplicate columns.');
        }

        foreach (self::REQUIRED_COLUMNS as $required) {
            if (!in_array($required, $columns, true)) {
                throw new RuntimeException(
                    'CSV header is missing required column: ' . $required
                );
            }
        }

        $allowed = array_merge(
            self::REQUIRED_COLUMNS,
            self::OPTIONAL_COLUMNS
        );

        foreach ($columns as $column) {
            if (!in_array($column, $allowed, true)) {
                throw new RuntimeException(
                    'CSV header contains unsupported column: ' . $column
                );
            }
        }
    }

    /**
     * @param list<string|null> $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (is_string($value) && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $columns
     * @param list<string|null> $row
     * @return array<string, string>
     */
    private function mapRow(
        array $columns,
        array $row,
        int $rowNumber
    ): array {
        if (count($row) > count($columns)) {
            throw new RuntimeException(
                'CSV row ' . $rowNumber . ' has too many columns.'
            );
        }

        $mapped = [];

        foreach ($columns as $index => $column) {
            $value = $row[$index] ?? '';

            if (!is_string($value)) {
                throw new RuntimeException(
                    'CSV row ' . $rowNumber . ' contains an invalid value.'
                );
            }

            $value = trim($value);

            if (mb_strlen($value) > self::MAX_FIELD_LENGTH) {
                throw new RuntimeException(
                    'CSV row ' . $rowNumber . ' contains an oversized field.'
                );
            }

            $mapped[$column] = $value;
        }

        return $mapped;
    }

    /**
     * @param array<string, string> $row
     * @param list<string> $warnings
     */
    private function contactFromMappedRow(
        array $row,
        int $rowNumber,
        array &$warnings
    ): ?ImportPreviewContact {
        $displayName = $row['display_name'] ?? '';

        if ($displayName === '') {
            $warnings[] = 'Row ' . $rowNumber
                . ' was skipped because display_name is empty.';

            return null;
        }

        $phoneNumbers = [];

        for ($index = 1; $index <= 3; $index++) {
            $number = $row['phone_' . $index] ?? '';

            if ($number === '') {
                continue;
            }

            $label = $this->optional(
                $row['phone_' . $index . '_label'] ?? ''
            );

            $phoneNumbers[] = new ImportPreviewPhoneNumber(
                $label,
                $number,
            );
        }

        return new ImportPreviewContact(
            $displayName,
            $this->optional($row['given_name'] ?? ''),
            $this->optional($row['family_name'] ?? ''),
            $this->optional($row['organization_name'] ?? ''),
            $this->optional($row['notes'] ?? ''),
            $phoneNumbers,
        );
    }

    private function optional(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
