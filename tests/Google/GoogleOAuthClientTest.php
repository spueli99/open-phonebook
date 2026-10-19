<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\Google;

use OpenPhonebook\Google\GoogleOAuthClient;
use OpenPhonebook\Google\GoogleOAuthState;
use OpenPhonebook\Http\HttpResponse;
use OpenPhonebook\Tests\Support\Http\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthClientTest extends TestCase
{
    public function testAuthorizationUrlUsesReadOnlyScopeAndPkce(): void
    {
        $client = new GoogleOAuthClient(
            new FakeHttpClient(),
            'client-id',
            'client-secret'
        );
        $state = new GoogleOAuthState(
            'state-value',
            str_repeat('v', 50)
        );
        $url = $client->authorizationUrl(
            'https://example.test/sources/google/callback/',
            $state
        );

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertSame(
            GoogleOAuthClient::SCOPE,
            $query['scope'] ?? null
        );
        self::assertSame('offline', $query['access_type'] ?? null);
        self::assertSame('consent', $query['prompt'] ?? null);
        self::assertSame('S256', $query['code_challenge_method'] ?? null);
        self::assertSame('state-value', $query['state'] ?? null);
        self::assertArrayHasKey('code_challenge', $query);
    }

    public function testRefreshUsesRefreshTokenGrant(): void
    {
        $http = new FakeHttpClient();
        $http->queue(new HttpResponse(
            200,
            json_encode([
                'access_token' => 'access',
                'expires_in' => 3600,
            ], JSON_THROW_ON_ERROR)
        ));
        $client = new GoogleOAuthClient(
            $http,
            'client-id',
            'client-secret'
        );

        $tokens = $client->refresh('refresh');

        self::assertSame('access', $tokens->accessToken);
        self::assertNull($tokens->refreshToken);
        self::assertSame(
            'refresh_token',
            $http->requests[0]['form']['grant_type']
        );
        self::assertSame(
            'refresh',
            $http->requests[0]['form']['refresh_token']
        );
    }
}
