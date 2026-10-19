<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

use OpenPhonebook\Sources\ContactSourceRepositoryInterface;
use Throwable;

final class GoogleContactsScheduler
{
    public function __construct(
        private readonly ContactSourceRepositoryInterface $sources,
        private readonly GoogleContactSourceSynchronizerInterface $synchronizer,
    ) {
    }

    public function run(): GoogleContactsSchedulerResult
    {
        $attempted = 0;
        $succeeded = 0;
        $failedSourceIds = [];

        foreach ($this->sources->allGoogleSources() as $source) {
            $attempted++;

            try {
                $this->synchronizer->syncSource($source);
                $succeeded++;
            } catch (Throwable) {
                $failedSourceIds[] = $source->id;
            }
        }

        return new GoogleContactsSchedulerResult(
            $attempted,
            $succeeded,
            $failedSourceIds,
        );
    }
}
