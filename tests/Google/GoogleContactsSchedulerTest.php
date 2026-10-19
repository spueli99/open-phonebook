<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Google;

use OpenPhonebook\Google\GoogleContactsScheduler;
use OpenPhonebook\Sources\ContactSource;
use OpenPhonebook\Tests\Support\Google\FakeGoogleContactSourceSynchronizer;
use OpenPhonebook\Tests\Support\Sources\InMemoryContactSourceRepository;
use PHPUnit\Framework\TestCase;

final class GoogleContactsSchedulerTest extends TestCase
{
    public function testContinuesAfterOneSourceFails(): void
    {
        $sources = new InMemoryContactSourceRepository();
        $sources->addSource($this->source(1));
        $sources->addSource($this->source(2));
        $sources->addSource($this->source(3));

        $synchronizer = new FakeGoogleContactSourceSynchronizer([2]);
        $result = (new GoogleContactsScheduler(
            $sources,
            $synchronizer
        ))->run();

        self::assertSame(3, $result->attempted);
        self::assertSame(2, $result->succeeded);
        self::assertSame(1, $result->failed());
        self::assertSame([2], $result->failedSourceIds);
        self::assertSame(
            [1, 2, 3],
            $synchronizer->syncedSourceIds
        );
    }

    public function testEmptySchedulerRunSucceeds(): void
    {
        $result = (new GoogleContactsScheduler(
            new InMemoryContactSourceRepository(),
            new FakeGoogleContactSourceSynchronizer()
        ))->run();

        self::assertSame(0, $result->attempted);
        self::assertSame(0, $result->failed());
    }

    private function source(int $id): ContactSource
    {
        return new ContactSource(
            $id,
            10,
            20 + $id,
            'google',
            'encrypted-refresh',
            null
        );
    }
}
