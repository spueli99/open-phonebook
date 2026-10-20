<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Auth;

use OpenPhonebook\Auth\PasswordResetToken;
use OpenPhonebook\UserManagement\InvitationToken;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenTest extends TestCase
{
    public function testResetTokenUsesDomainSeparatedHash(): void
    {
        $key =
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $plain = str_repeat('1', 64);

        self::assertNotSame(
            PasswordResetToken::hash($plain, $key),
            InvitationToken::hash($plain, $key)
        );
    }
}
