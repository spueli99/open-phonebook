<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Import;

use OpenPhonebook\Import\CsvContactImportParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CsvContactImportParserTest extends TestCase
{
    public function testParsesSupportedContactFields(): void
    {
        $csv = implode("\n", [
            'display_name,given_name,family_name,organization_name,notes,phone_1,phone_1_label',
            'Alice Example,Alice,Example,Example GmbH,Friend,+49 170 1234567,Mobile',
        ]);

        $preview = (new CsvContactImportParser())->parse($csv);

        self::assertCount(1, $preview->contacts);
        self::assertSame([], $preview->warnings);
        self::assertSame(
            'Alice Example',
            $preview->contacts[0]->displayName
        );
        self::assertSame(
            'Example GmbH',
            $preview->contacts[0]->organizationName
        );
        self::assertSame(
            '+49 170 1234567',
            $preview->contacts[0]->phoneNumbers[0]->rawNumber
        );
        self::assertSame(
            'Mobile',
            $preview->contacts[0]->phoneNumbers[0]->label
        );
    }

    public function testSkipsBlankDisplayNameWithWarning(): void
    {
        $csv = implode("\n", [
            'display_name,phone_1',
            ',0211 123456',
            'Alice,0211 654321',
        ]);

        $preview = (new CsvContactImportParser())->parse($csv);

        self::assertCount(1, $preview->contacts);
        self::assertCount(1, $preview->warnings);
        self::assertSame('Alice', $preview->contacts[0]->displayName);
    }

    public function testRejectsUnknownColumns(): void
    {
        $this->expectException(RuntimeException::class);

        (new CsvContactImportParser())->parse(
            "display_name,password\nAlice,secret"
        );
    }

    public function testRejectsDuplicateColumns(): void
    {
        $this->expectException(RuntimeException::class);

        (new CsvContactImportParser())->parse(
            "display_name,display_name\nAlice,Alice"
        );
    }

    public function testRejectsInvalidUtf8(): void
    {
        $this->expectException(RuntimeException::class);

        (new CsvContactImportParser())->parse(
            "display_name\nAlice\xFF"
        );
    }

    public function testRejectsBinaryNulByte(): void
    {
        $this->expectException(RuntimeException::class);

        (new CsvContactImportParser())->parse(
            "display_name\nAli\0ce"
        );
    }

    public function testRejectsOversizedDisplayName(): void
    {
        $this->expectException(RuntimeException::class);

        (new CsvContactImportParser())->parse(
            "display_name\n" . str_repeat('a', 256)
        );
    }

    public function testRejectsTooManyRows(): void
    {
        $rows = ['display_name'];

        for ($index = 0; $index <= CsvContactImportParser::MAX_ROWS; $index++) {
            $rows[] = 'Contact ' . $index;
        }

        $this->expectException(RuntimeException::class);

        (new CsvContactImportParser())->parse(implode("\n", $rows));
    }

    public function testSupportsUtf8BomInFirstHeaderColumn(): void
    {
        $preview = (new CsvContactImportParser())->parse(
            "\xEF\xBB\xBFdisplay_name\nAlice"
        );

        self::assertSame('Alice', $preview->contacts[0]->displayName);
    }
}
