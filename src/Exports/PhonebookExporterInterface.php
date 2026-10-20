<?php

declare(strict_types=1);

namespace OpenPhonebook\Exports;

interface PhonebookExporterInterface
{
    public function format(): string;

    /**
     * @param list<PhonebookEntry> $entries
     */
    public function export(array $entries): PhonebookExport;
}
