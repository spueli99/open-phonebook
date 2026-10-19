<?php

declare(strict_types=1);

namespace OpenPhonebook\I18n;

final class Translator
{
    /**
     * @param array<string, string> $messages
     */
    private function __construct(
        private readonly array $messages,
        public readonly string $locale,
    ) {
    }

    public static function load(string $projectRoot, string $locale): self
    {
        $locale = in_array($locale, ['en', 'de'], true) ? $locale : 'en';
        $path = $projectRoot . '/translations/' . $locale . '.php';

        /** @var array<string, string> $messages */
        $messages = require $path;

        return new self($messages, $locale);
    }

    public function get(string $key): string
    {
        return $this->messages[$key] ?? $key;
    }
}
