<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Import;

use OpenPhonebook\Import\VCardContactImportParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class VCardContactImportParserTest extends TestCase
{
    public function testParsesVCardThreeWithNamesOrganizationAndPhones(): void
    {
        $contents = implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:Alice Example',
            'N:Example;Alice;;;',
            'ORG:Example GmbH',
            'NOTE:Imported contact',
            'TEL;TYPE=CELL:+49 170 1234567',
            'TEL;TYPE=WORK,VOICE:+49 211 7654321',
            'END:VCARD',
            '',
        ]);

        $preview = (new VCardContactImportParser())->parse($contents);

        self::assertCount(1, $preview->contacts);
        $contact = $preview->contacts[0];

        self::assertSame('Alice Example', $contact->displayName);
        self::assertSame('Alice', $contact->givenName);
        self::assertSame('Example', $contact->familyName);
        self::assertSame('Example GmbH', $contact->organizationName);
        self::assertSame('Imported contact', $contact->notes);
        self::assertCount(2, $contact->phoneNumbers);
        self::assertSame('CELL', $contact->phoneNumbers[0]->label);
        self::assertSame(
            'WORK, VOICE',
            $contact->phoneNumbers[1]->label
        );
    }

    public function testParsesVCardFourTelUriAndFoldedNote(): void
    {
        $contents = implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:4.0',
            'FN:Bob Example',
            'N:Example;Bob;;;',
            'NOTE:This is a long note that is',
            ' folded over two physical lines.',
            'TEL;TYPE=cell:tel:+491701234567',
            'END:VCARD',
            '',
        ]);

        $preview = (new VCardContactImportParser())->parse($contents);
        $contact = $preview->contacts[0];

        self::assertSame(
            'This is a long note that isfolded over two physical lines.',
            $contact->notes
        );
        self::assertSame(
            '+491701234567',
            $contact->phoneNumbers[0]->rawNumber
        );
        self::assertSame('cell', $contact->phoneNumbers[0]->label);
    }

    public function testParsesMultipleCardsAndVCardTwoPointOne(): void
    {
        $contents = implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:2.1',
            'N:Example;Alice;;;',
            'TEL;HOME:0211 123456',
            'END:VCARD',
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:Bob Example',
            'TEL;TYPE=CELL:+49 170 1234567',
            'END:VCARD',
            '',
        ]);

        $preview = (new VCardContactImportParser())->parse($contents);

        self::assertCount(2, $preview->contacts);
        self::assertSame(
            'Alice Example',
            $preview->contacts[0]->displayName
        );
        self::assertSame(
            'Bob Example',
            $preview->contacts[1]->displayName
        );
    }

    public function testFallsBackToOrganizationForDisplayName(): void
    {
        $contents = implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'ORG:Example GmbH',
            'TEL;TYPE=WORK:+49 211 7654321',
            'END:VCARD',
            '',
        ]);

        $preview = (new VCardContactImportParser())->parse($contents);

        self::assertSame(
            'Example GmbH',
            $preview->contacts[0]->displayName
        );
    }

    public function testSkipsCardWithoutUsableDisplayName(): void
    {
        $contents = implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'TEL;TYPE=CELL:+49 170 1234567',
            'END:VCARD',
            '',
        ]);

        $preview = (new VCardContactImportParser())->parse($contents);

        self::assertSame([], $preview->contacts);
        self::assertCount(1, $preview->warnings);
    }

    public function testSupportsUtf8BomBeforeFirstCard(): void
    {
        $preview = (new VCardContactImportParser())->parse(
            "\xEF\xBB\xBFBEGIN:VCARD\r\n"
            . "VERSION:3.0\r\n"
            . "FN:Alice\r\n"
            . "END:VCARD\r\n"
        );

        self::assertSame('Alice', $preview->contacts[0]->displayName);
    }

    public function testFoldedEndMarkerTextDoesNotCloseCard(): void
    {
        $contents = implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:Alice',
            'NOTE:Literal text',
            ' END:VCARD',
            'TEL;TYPE=CELL:+49 170 1234567',
            'END:VCARD',
            '',
        ]);

        $preview = (new VCardContactImportParser())->parse($contents);

        self::assertCount(1, $preview->contacts);
        self::assertCount(
            1,
            $preview->contacts[0]->phoneNumbers
        );
    }

    public function testRejectsDataOutsideVCard(): void
    {
        $this->expectException(RuntimeException::class);

        (new VCardContactImportParser())->parse(
            "unexpected\nBEGIN:VCARD\nVERSION:3.0\nFN:Alice\nEND:VCARD"
        );
    }

    public function testRejectsIncompleteVCard(): void
    {
        $this->expectException(RuntimeException::class);

        (new VCardContactImportParser())->parse(
            "BEGIN:VCARD\nVERSION:3.0\nFN:Alice"
        );
    }

    public function testRejectsInvalidUtf8(): void
    {
        $this->expectException(RuntimeException::class);

        (new VCardContactImportParser())->parse(
            "BEGIN:VCARD\nVERSION:3.0\nFN:Ali\xFFce\nEND:VCARD"
        );
    }

    public function testRejectsBinaryNulByte(): void
    {
        $this->expectException(RuntimeException::class);

        (new VCardContactImportParser())->parse(
            "BEGIN:VCARD\nVERSION:3.0\nFN:Ali\0ce\nEND:VCARD"
        );
    }
}
