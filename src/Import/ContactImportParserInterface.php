<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

interface ContactImportParserInterface
{
    public function parse(string $contents): ImportPreviewResult;
}
