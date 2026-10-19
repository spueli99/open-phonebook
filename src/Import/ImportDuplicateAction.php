<?php

declare(strict_types=1);

namespace OpenPhonebook\Import;

enum ImportDuplicateAction: string
{
    case Skip = 'skip';
    case Import = 'import';
    case Merge = 'merge';
}
