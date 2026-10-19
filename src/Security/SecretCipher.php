<?php

declare(strict_types=1);

namespace OpenPhonebook\Security;

use RuntimeException;

final class SecretCipher
{
    private const CIPHER = 'aes-256-gcm';
    private const VERSION = 'v1';
    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;

    private readonly string $key;

    public function __construct(string $applicationKey)
    {
        if (strlen($applicationKey) < 32) {
            throw new RuntimeException('Application key is too short.');
        }

        $this->key = hash_hkdf(
            'sha256',
            $applicationKey,
            32,
            'open-phonebook/provider-secrets/v1'
        );
    }

    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new RuntimeException('Secret must not be empty.');
        }

        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::VERSION,
            self::TAG_BYTES
        );

        if (!is_string($ciphertext) || strlen($tag) !== self::TAG_BYTES) {
            throw new RuntimeException('Secret encryption failed.');
        }

        return self::VERSION . '.'
            . $this->base64UrlEncode($nonce . $tag . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        $prefix = self::VERSION . '.';

        if (!str_starts_with($encoded, $prefix)) {
            throw new RuntimeException('Unsupported encrypted secret format.');
        }

        $payload = $this->base64UrlDecode(substr($encoded, strlen($prefix)));

        if (strlen($payload) <= self::NONCE_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('Encrypted secret is invalid.');
        }

        $nonce = substr($payload, 0, self::NONCE_BYTES);
        $tag = substr($payload, self::NONCE_BYTES, self::TAG_BYTES);
        $ciphertext = substr(
            $payload,
            self::NONCE_BYTES + self::TAG_BYTES
        );
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::VERSION
        );

        if (!is_string($plaintext) || $plaintext === '') {
            throw new RuntimeException('Secret decryption failed.');
        }

        return $plaintext;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(
            strtr(base64_encode($value), '+/', '-_'),
            '='
        );
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(
            strtr($value, '-_', '+/'),
            true
        );

        if (!is_string($decoded)) {
            throw new RuntimeException('Encrypted secret is invalid.');
        }

        return $decoded;
    }
}
