<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Web;

use OpenPhonebook\Web\Request;
use PHPUnit\Framework\TestCase;

final class RequestGetTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
    }

    public function testReadsTrimmedStringAndPositiveInteger(): void
    {
        $_GET = [
            'status' => ' failed ',
            'before' => '42',
        ];

        self::assertSame('failed', Request::getString('status'));
        self::assertSame(42, Request::getPositiveInt('before'));
    }

    public function testRejectsInvalidPositiveInteger(): void
    {
        $_GET = ['before' => '-1'];

        self::assertSame(0, Request::getPositiveInt('before'));

        $_GET = ['before' => ['42']];

        self::assertSame('', Request::getString('before'));
        self::assertSame(0, Request::getPositiveInt('before'));
    }
}
