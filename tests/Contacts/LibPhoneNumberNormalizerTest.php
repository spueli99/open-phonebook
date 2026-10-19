<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Contacts;

use OpenPhonebook\Contacts\LibPhoneNumberNormalizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LibPhoneNumberNormalizerTest extends TestCase
{
    public function testNormalizesGermanLocalNumberWithExplicitRegion(): void
    {
        $normalizer = new LibPhoneNumberNormalizer('DE');

        self::assertSame(
            '+491701234567',
            $normalizer->normalize('0170 1234567')
        );
    }

    public function testNormalizesInternationalNumberWithoutDefaultRegion(): void
    {
        $normalizer = new LibPhoneNumberNormalizer(null);

        self::assertSame(
            '+491701234567',
            $normalizer->normalize('+49 170 1234567')
        );
    }

    public function testLocalNumberWithoutRegionIsNotGuessed(): void
    {
        $normalizer = new LibPhoneNumberNormalizer(null);

        self::assertNull($normalizer->normalize('0211 123456'));
    }

    public function testInvalidNumberReturnsNull(): void
    {
        $normalizer = new LibPhoneNumberNormalizer('DE');

        self::assertNull($normalizer->normalize('not-a-number'));
    }

    public function testInvalidRegionIsRejected(): void
    {
        $this->expectException(RuntimeException::class);

        new LibPhoneNumberNormalizer('ZZ');
    }
}
