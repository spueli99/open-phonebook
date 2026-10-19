<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

enum ContactImportFormat: string
{
    case Csv = 'csv';
    case VCard = 'vcard';
}
