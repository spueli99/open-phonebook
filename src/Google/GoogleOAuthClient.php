<?php

declare(strict_types=1);

namespace OpenPhonebook\Google;

use OpenPhonebook\Http\HttpClientInterface;
use RuntimeException;

final class GoogleOAuthClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/contacts.readonly';

    private const AUTHORIZATION_ENDPOINT =
        'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT =
        'https://oauth2.googleapis.com/token';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException(
                'Google OAuth credentials are not configured.'
            );
        }
    }

    public function authorizationUrl(
        string $redirectUri,
        GoogleOAuthState $state
    ): string {
        return self::AUTHORIZATION_ENDPOINT . '?'
            . http_build_query([
                'client_id' => $this->clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => self::SCOPE,
                'access_type' => 'offline',
                'include_granted_scopes' => 'true',
                'prompt' => 'consent',
                'state' => $state->state,
                'code_challenge' => $state->codeChallenge(),
                'code_challenge_method' => 'S256',
            ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(
        string $code,
        string $redirectUri,
        string $codeVerifier
    ): GoogleTokenSet {
        if ($code === '' || $codeVerifier === '') {
            throw new RuntimeException('Google OAuth response is invalid.');
        }

        return $this->tokenRequest([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);
    }

    public function refresh(string $refreshToken): GoogleTokenSet
    {
        if ($refreshToken === '') {
            throw new RuntimeException('Google refresh token is missing.');
        }

        return $this->tokenRequest([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
    }

    /**
     * @param array<string, string> $form
     */
    private function tokenRequest(array $form): GoogleTokenSet
    {
        $response = $this->http->postForm(
            self::TOKEN_ENDPOINT,
            ['Accept' => 'application/json'],
            $form
        );

        if ($response->statusCode !== 200) {
            throw new RuntimeException('Google OAuth token request failed.');
        }

        $payload = json_decode(
            $response->body,
            true,
            32,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($payload)) {
            throw new RuntimeException('Google OAuth response is invalid.');
        }

        $accessToken = $payload['access_token'] ?? null;
        $refreshToken = $payload['refresh_token'] ?? null;
        $expiresIn = $payload['expires_in'] ?? null;

        if (
            !is_string($accessToken)
            || $accessToken === ''
            || !is_int($expiresIn)
            || $expiresIn <= 0
            || (
                $refreshToken !== null
                && (
                    !is_string($refreshToken)
                    || $refreshToken === ''
                )
            )
        ) {
            throw new RuntimeException('Google OAuth response is invalid.');
        }

        return new GoogleTokenSet(
            $accessToken,
            $refreshToken,
            $expiresIn,
        );
    }
}
