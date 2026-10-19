<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Web;

use OpenPhonebook\Web\Request;
use PHPUnit\Framework\TestCase;

final class RequestIntListTest extends TestCase
{
    protected function tearDown(): void
    {
        $_POST = [];
    }

    public function testAcceptsOnlyUniqueNonNegativeIntegerStrings(): void
    {
        $_POST = [
            'rows' => ['0', '2', '2', '-1', '1.5', 'abc', '03'],
        ];

        self::assertSame(
            [0, 2, 3],
            Request::postIntList('rows')
        );
    }

    public function testMissingOrScalarValueProducesEmptyList(): void
    {
        $_POST = [];

        self::assertSame([], Request::postIntList('rows'));

        $_POST = ['rows' => '1'];

        self::assertSame([], Request::postIntList('rows'));
    }
}
