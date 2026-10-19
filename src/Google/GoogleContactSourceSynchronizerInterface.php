<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

use OpenPhonebook\Sources\ContactSource;

interface GoogleContactSourceSynchronizerInterface
{
    public function syncSource(
        ContactSource $source
    ): GoogleContactsSyncResult;
}
