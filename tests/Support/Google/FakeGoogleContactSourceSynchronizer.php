<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Support\Google;

use OpenPhonebook\Google\GoogleContactsSyncResult;
use OpenPhonebook\Google\GoogleContactSourceSynchronizerInterface;
use OpenPhonebook\Sources\ContactSource;
use RuntimeException;

final class FakeGoogleContactSourceSynchronizer implements GoogleContactSourceSynchronizerInterface
{
    /**
     * @var list<int>
     */
    public array $syncedSourceIds = [];

    /**
     * @param list<int> $failingSourceIds
     */
    public function __construct(
        private readonly array $failingSourceIds = []
    ) {
    }

    public function syncSource(
        ContactSource $source
    ): GoogleContactsSyncResult {
        $this->syncedSourceIds[] = $source->id;

        if (in_array($source->id, $this->failingSourceIds, true)) {
            throw new RuntimeException('Simulated sync failure.');
        }

        return new GoogleContactsSyncResult(0, 0, 0, 0);
    }
}
