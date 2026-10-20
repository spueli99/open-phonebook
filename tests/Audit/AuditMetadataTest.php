<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Audit;

use InvalidArgumentException;
use OpenPhonebook\Audit\AuditMetadata;
use PHPUnit\Framework\TestCase;

final class AuditMetadataTest extends TestCase
{
    public function testAcceptsSmallNonSensitiveMetadata(): void
    {
        self::assertSame(
            ['previous_role' => 'member', 'new_role' => 'system_admin'],
            AuditMetadata::validate([
                'previous_role' => 'member',
                'new_role' => 'system_admin',
            ])
        );
    }

    public function testRejectsSensitiveMetadataKeys(): void
    {
        $rejected = 0;

        foreach (
            ['password_hash', 'reset_token', 'session_id', 'email_address'] as $key
        ) {
            try {
                AuditMetadata::validate([$key => 'sensitive']);
                self::fail('Sensitive audit metadata key was accepted.');
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(4, $rejected);
    }

    public function testRejectsUnsupportedValueTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AuditMetadata::validate(['role' => ['member']]);
    }

    public function testRejectsTooManyMetadataEntries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AuditMetadata::validate([
            'a' => 1,
            'b' => 2,
            'c' => 3,
            'd' => 4,
            'e' => 5,
            'f' => 6,
            'g' => 7,
            'h' => 8,
            'i' => 9,
        ]);
    }
}
