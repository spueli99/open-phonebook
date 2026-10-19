<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

use RuntimeException;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Parameter;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;
use Throwable;

final class VCardContactImportParser implements ContactImportParserInterface
{
    public const MAX_BYTES = 2_000_000;
    public const MAX_CARDS = 2_000;
    public const MAX_FIELD_LENGTH = 10_000;

    public function parse(string $contents): ImportPreviewResult
    {
        if ($contents === '') {
            throw new RuntimeException('Import file is empty.');
        }

        if (strlen($contents) > self::MAX_BYTES) {
            throw new RuntimeException('Import file is too large.');
        }

        if (str_contains($contents, "\0")) {
            throw new RuntimeException(
                'Import file contains invalid binary data.'
            );
        }

        if (!mb_check_encoding($contents, 'UTF-8')) {
            throw new RuntimeException(
                'Import file must be valid UTF-8.'
            );
        }

        $cards = $this->splitCards($contents);

        if ($cards === []) {
            throw new RuntimeException('Import file contains no vCards.');
        }

        if (count($cards) > self::MAX_CARDS) {
            throw new RuntimeException('Import file has too many vCards.');
        }

        $contacts = [];
        $warnings = [];

        foreach ($cards as $index => $cardContents) {
            $cardNumber = $index + 1;
            $card = $this->parseCard($cardContents, $cardNumber);
            $contact = $this->mapCard(
                $card,
                $cardNumber,
                $warnings
            );

            if ($contact !== null) {
                $contacts[] = $contact;
            }
        }

        return new ImportPreviewResult($contacts, $warnings);
    }

    /**
     * @return list<string>
     */
    private function splitCards(string $contents): array
    {
        $normalized = str_replace(
            ["\r\n", "\r"],
            "\n",
            $contents
        );
        if (str_starts_with($normalized, "\xEF\xBB\xBF")) {
            $normalized = substr($normalized, 3);
        }

        $lines = explode("\n", $normalized);
        $cards = [];
        $current = [];
        $insideCard = false;

        foreach ($lines as $line) {
            if (!$insideCard) {
                if (trim($line) === '') {
                    continue;
                }

                if (strcasecmp($line, 'BEGIN:VCARD') !== 0) {
                    throw new RuntimeException(
                        'Import file contains data outside a vCard.'
                    );
                }

                $insideCard = true;
                $current = ['BEGIN:VCARD'];
                continue;
            }

            $current[] = $line;

            if (strcasecmp($line, 'END:VCARD') === 0) {
                $cards[] = implode("\r\n", $current) . "\r\n";
                $current = [];
                $insideCard = false;
            }
        }

        if ($insideCard) {
            throw new RuntimeException(
                'Import file contains an incomplete vCard.'
            );
        }

        return $cards;
    }

    private function parseCard(
        string $contents,
        int $cardNumber
    ): VCard {
        try {
            $document = Reader::read($contents);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'vCard ' . $cardNumber . ' could not be parsed.',
                0,
                $exception
            );
        }

        if (!$document instanceof VCard) {
            throw new RuntimeException(
                'vCard ' . $cardNumber . ' is not a valid vCard.'
            );
        }

        return $document;
    }

    /**
     * @param list<string> $warnings
     */
    private function mapCard(
        VCard $card,
        int $cardNumber,
        array &$warnings
    ): ?ImportPreviewContact {
        [$givenName, $familyName] = $this->structuredName($card);
        $organization = $this->firstTextPart($card, 'ORG');
        $displayName = $this->firstText($card, 'FN');

        if ($displayName === null) {
            $nameParts = [];

            if ($givenName !== null) {
                $nameParts[] = $givenName;
            }

            if ($familyName !== null) {
                $nameParts[] = $familyName;
            }

            $displayName = implode(' ', $nameParts);
        }

        if ($displayName === '' && $organization !== null) {
            $displayName = $organization;
        }

        if ($displayName === '') {
            $warnings[] = 'vCard ' . $cardNumber
                . ' was skipped because it has no display name.';

            return null;
        }

        $this->assertLength(
            $displayName,
            255,
            $cardNumber,
            'display name'
        );

        $phoneNumbers = [];

        foreach ($card->select('TEL') as $telephone) {
            if (!$telephone instanceof Property) {
                continue;
            }

            $number = trim((string) $telephone);

            if (str_starts_with(strtolower($number), 'tel:')) {
                $number = substr($number, 4);
            }

            if ($number === '') {
                continue;
            }

            $this->assertLength(
                $number,
                100,
                $cardNumber,
                'phone number'
            );

            $label = $this->phoneLabel($telephone);

            if ($label !== null) {
                $this->assertLength(
                    $label,
                    64,
                    $cardNumber,
                    'phone label'
                );
            }

            $phoneNumbers[] = new ImportPreviewPhoneNumber(
                $label,
                $number,
            );
        }

        $notes = $this->firstText($card, 'NOTE');

        if ($notes !== null) {
            $this->assertLength(
                $notes,
                self::MAX_FIELD_LENGTH,
                $cardNumber,
                'notes'
            );
        }

        return new ImportPreviewContact(
            $displayName,
            $this->boundedOptional(
                $givenName,
                120,
                $cardNumber,
                'given name'
            ),
            $this->boundedOptional(
                $familyName,
                120,
                $cardNumber,
                'family name'
            ),
            $this->boundedOptional(
                $organization,
                255,
                $cardNumber,
                'organization'
            ),
            $notes,
            $phoneNumbers,
        );
    }

    /**
     * @return array{?string, ?string}
     */
    private function structuredName(VCard $card): array
    {
        $properties = $card->select('N');

        $property = $properties[0] ?? null;

        if (!$property instanceof Property) {
            return [null, null];
        }

        $parts = $property->getParts();
        $familyName = $this->optionalText($parts[0] ?? null);
        $givenName = $this->optionalText($parts[1] ?? null);

        return [$givenName, $familyName];
    }

    private function firstText(
        VCard $card,
        string $propertyName
    ): ?string {
        $properties = $card->select($propertyName);

        $property = $properties[0] ?? null;

        if (!$property instanceof Property) {
            return null;
        }

        return $this->optionalText((string) $property);
    }

    private function firstTextPart(
        VCard $card,
        string $propertyName
    ): ?string {
        $properties = $card->select($propertyName);

        $property = $properties[0] ?? null;

        if (!$property instanceof Property) {
            return null;
        }

        $parts = $property->getParts();

        return $this->optionalText($parts[0] ?? null);
    }

    private function phoneLabel(Property $telephone): ?string
    {
        $type = $telephone['TYPE'] ?? null;

        if (!$type instanceof Parameter) {
            return null;
        }

        $parts = $type->getParts();
        $labels = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);

            if ($part !== '' && strcasecmp($part, 'PREF') !== 0) {
                $labels[] = $part;
            }
        }

        if ($labels === []) {
            return null;
        }

        return implode(', ', $labels);
    }

    private function boundedOptional(
        ?string $value,
        int $maxLength,
        int $cardNumber,
        string $field
    ): ?string {
        if ($value === null) {
            return null;
        }

        $this->assertLength(
            $value,
            $maxLength,
            $cardNumber,
            $field
        );

        return $value;
    }

    private function assertLength(
        string $value,
        int $maxLength,
        int $cardNumber,
        string $field
    ): void {
        if (mb_strlen($value) > $maxLength) {
            throw new RuntimeException(
                'vCard ' . $cardNumber . ' contains an oversized '
                . $field . '.'
            );
        }
    }

    private function optionalText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
