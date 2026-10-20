<?php

declare(strict_types=1);

namespace OpenPhonebook\Exports;

use RuntimeException;

final class PhonebookExporterRegistry
{
    /** @var array<string, PhonebookExporterInterface> */
    private array $exporters = [];

    /**
     * @param list<PhonebookExporterInterface> $exporters
     */
    public function __construct(array $exporters)
    {
        foreach ($exporters as $exporter) {
            $format = $exporter->format();

            if (
                !preg_match('/^[a-z0-9_-]+$/', $format)
                || isset($this->exporters[$format])
            ) {
                throw new RuntimeException(
                    'Invalid or duplicate phonebook export format.'
                );
            }

            $this->exporters[$format] = $exporter;
        }
    }

    public function get(string $format): ?PhonebookExporterInterface
    {
        return $this->exporters[$format] ?? null;
    }
}
