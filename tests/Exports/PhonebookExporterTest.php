<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Exports;

use DOMDocument;
use OpenPhonebook\Exports\CsvPhonebookExporter;
use OpenPhonebook\Exports\JsonPhonebookExporter;
use OpenPhonebook\Exports\PhonebookEntry;
use OpenPhonebook\Exports\PhonebookPhoneNumber;
use OpenPhonebook\Exports\YealinkPhonebookExporter;
use PHPUnit\Framework\TestCase;

final class PhonebookExporterTest extends TestCase
{
    /**
     * @return list<PhonebookEntry>
     */
    private function entries(): array
    {
        return [
            new PhonebookEntry(
                'Alice & Bob',
                [
                    new PhonebookPhoneNumber('+491701234567', 'mobile'),
                    new PhonebookPhoneNumber('0211 1234', null),
                ]
            ),
        ];
    }

    public function testJsonExporterProducesStableSchema(): void
    {
        $export = (new JsonPhonebookExporter())->export($this->entries());
        $decoded = json_decode($export->body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $decoded['version']);
        self::assertSame('Alice & Bob', $decoded['contacts'][0]['name']);
        self::assertSame(
            '+491701234567',
            $decoded['contacts'][0]['numbers'][0]['number']
        );
    }

    public function testCsvExporterEscapesSpreadsheetFormulas(): void
    {
        $export = (new CsvPhonebookExporter())->export([
            new PhonebookEntry(
                '=2+2',
                [new PhonebookPhoneNumber('+49123', '@mobile')]
            ),
        ]);

        self::assertStringContainsString("'=2+2", $export->body);
        self::assertStringContainsString("'+49123", $export->body);
        self::assertStringContainsString("'@mobile", $export->body);
    }

    public function testYealinkExporterProducesValidDirectoryXml(): void
    {
        $export = (new YealinkPhonebookExporter())->export($this->entries());
        $document = new DOMDocument();

        self::assertTrue($document->loadXML($export->body));
        self::assertSame(
            'YealinkIPPhoneDirectory',
            $document->documentElement?->nodeName
        );
        self::assertSame(
            2,
            $document->getElementsByTagName('Telephone')->length
        );
    }
}
