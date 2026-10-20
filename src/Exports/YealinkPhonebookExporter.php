<?php

declare(strict_types=1);

namespace OpenPhonebook\Exports;

use DOMDocument;
use RuntimeException;

final class YealinkPhonebookExporter implements PhonebookExporterInterface
{
    private const MAX_BYTES = 1_500_000;

    public function format(): string
    {
        return 'yealink';
    }

    public function export(array $entries): PhonebookExport
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElement('YealinkIPPhoneDirectory');
        $document->appendChild($root);

        foreach ($entries as $entry) {
            $directoryEntry = $document->createElement('DirectoryEntry');
            $directoryEntry->appendChild(
                $document->createElement('Name', $entry->displayName)
            );

            foreach ($entry->phoneNumbers as $phoneNumber) {
                $directoryEntry->appendChild(
                    $document->createElement(
                        'Telephone',
                        $phoneNumber->number
                    )
                );
            }

            $root->appendChild($directoryEntry);
        }

        $body = $document->saveXML();

        if ($body === false) {
            throw new RuntimeException(
                'Yealink phonebook XML encoding failed.'
            );
        }

        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException(
                'Yealink phonebook exceeds the supported size limit.'
            );
        }

        return new PhonebookExport(
            $body,
            'application/xml; charset=utf-8',
            'phonebook.xml'
        );
    }
}
