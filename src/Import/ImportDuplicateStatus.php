<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

enum ImportDuplicateStatus: string
{
    case None = 'none';
    case ExistingContact = 'existing_contact';
    case WithinImport = 'within_import';

    public function isDuplicate(): bool
    {
        return $this !== self::None;
    }
}
