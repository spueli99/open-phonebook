<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Devices;

use OpenPhonebook\Devices\DeviceCredentialAuthenticator;
use OpenPhonebook\Devices\DeviceCredentialHttpAuthenticator;
use OpenPhonebook\Devices\DeviceCredentialScope;
use OpenPhonebook\Devices\DeviceCredentialToken;
use OpenPhonebook\Tests\Support\Devices\InMemoryDeviceCredentialRepository;
use PHPUnit\Framework\TestCase;

final class DeviceCredentialHttpAuthenticatorTest extends TestCase
{
    private const APP_KEY =
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testBearerAndBasicUseSameScopedCredential(): void
    {
        $credentials = new InMemoryDeviceCredentialRepository();
        $credentials->setOwner(7, 10);
        $token = DeviceCredentialToken::generate(self::APP_KEY);
        $credentials->create(
            7,
            DeviceCredentialScope::PhonebookRead,
            $token->hash
        );
        $authentication = new DeviceCredentialHttpAuthenticator(
            new DeviceCredentialAuthenticator(
                $credentials,
                self::APP_KEY
            )
        );

        $bearer = $authentication->authenticate(
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token->plainText],
            DeviceCredentialScope::PhonebookRead
        );
        $basic = $authentication->authenticate(
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode(
                    'device:' . $token->plainText
                ),
            ],
            DeviceCredentialScope::PhonebookRead
        );

        self::assertNotNull($bearer);
        self::assertNotNull($basic);
        self::assertSame(7, $bearer->deviceId);
        self::assertSame(7, $basic->deviceId);
    }

    public function testCredentialsAreNeverAcceptedFromOtherInputs(): void
    {
        $credentials = new InMemoryDeviceCredentialRepository();
        $authentication = new DeviceCredentialHttpAuthenticator(
            new DeviceCredentialAuthenticator(
                $credentials,
                self::APP_KEY
            )
        );

        self::assertNull(
            $authentication->authenticate(
                ['QUERY_STRING' => 'token=' . str_repeat('a', 64)],
                DeviceCredentialScope::PhonebookRead
            )
        );
        self::assertNull(
            $authentication->authenticate(
                ['HTTP_AUTHORIZATION' => 'Basic !!!'],
                DeviceCredentialScope::PhonebookRead
            )
        );
    }
}
