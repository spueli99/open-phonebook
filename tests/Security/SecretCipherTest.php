<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Security;

use OpenPhonebook\Security\SecretCipher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecretCipherTest extends TestCase
{
    public function testEncryptsAndDecryptsSecret(): void
    {
        $cipher = new SecretCipher(str_repeat('a', 64));
        $encrypted = $cipher->encrypt('refresh-token');

        self::assertNotSame('refresh-token', $encrypted);
        self::assertSame(
            'refresh-token',
            $cipher->decrypt($encrypted)
        );
    }

    public function testTamperedCiphertextIsRejected(): void
    {
        $cipher = new SecretCipher(str_repeat('a', 64));
        $encrypted = $cipher->encrypt('refresh-token');
        $last = substr($encrypted, -1);
        $tampered = substr($encrypted, 0, -1)
            . ($last === 'A' ? 'B' : 'A');

        $this->expectException(RuntimeException::class);
        $cipher->decrypt($tampered);
    }

    public function testWrongApplicationKeyCannotDecrypt(): void
    {
        $encrypted = (new SecretCipher(
            str_repeat('a', 64)
        ))->encrypt('refresh-token');

        $this->expectException(RuntimeException::class);
        (new SecretCipher(str_repeat('b', 64)))->decrypt($encrypted);
    }
}
