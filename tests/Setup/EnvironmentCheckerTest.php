<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Setup;

use OpenPhonebook\Setup\EnvironmentChecker;
use PHPUnit\Framework\TestCase;

final class EnvironmentCheckerTest extends TestCase
{
    public function testHttpsFailurePreventsAllPassed(): void
    {
        $checker = new EnvironmentChecker();
        $checks = $checker->check(dirname(__DIR__, 2), false);

        self::assertFalse($checker->allPassed($checks));
    }
}
