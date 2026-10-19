<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

use RuntimeException;

final class ContactImportParserRegistry
{
    /**
     * @param array<string, ContactImportParserInterface> $parsers
     */
    public function __construct(private readonly array $parsers)
    {
    }

    public function parserFor(
        ContactImportFormat $format
    ): ContactImportParserInterface {
        $parser = $this->parsers[$format->value] ?? null;

        if ($parser === null) {
            throw new RuntimeException('Unsupported contact import format.');
        }

        return $parser;
    }
}
