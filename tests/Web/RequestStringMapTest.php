<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Web;

use OpenPhonebook\Web\Request;
use PHPUnit\Framework\TestCase;

final class RequestStringMapTest extends TestCase
{
    protected function tearDown(): void
    {
        $_POST = [];
    }

    public function testMapsStringValuesAndTrimsThem(): void
    {
        $_POST = [
            'actions' => [
                '0' => ' skip ',
                '2' => 'merge',
                'invalid' => ['nested'],
            ],
        ];

        self::assertSame(
            [
                '0' => 'skip',
                '2' => 'merge',
            ],
            Request::postStringMap('actions')
        );
    }

    public function testScalarOrMissingInputProducesEmptyMap(): void
    {
        $_POST = ['actions' => 'merge'];
        self::assertCount(0, Request::postStringMap('actions'));

        $_POST = [];
        self::assertCount(0, Request::postStringMap('actions'));
    }
}
