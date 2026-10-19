<?php

declare(strict_types=1);

namespace OpenPhonebook\Tests\I18n;

use OpenPhonebook\I18n\Translator;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    public function testGermanTranslationCanBeLoaded(): void
    {
        $translator = Translator::load(dirname(__DIR__, 2), 'de');

        self::assertSame('de', $translator->locale);
        self::assertSame(
            'Anmelden',
            $translator->get('auth.login.title')
        );
    }

    public function testUnknownLocaleFallsBackToEnglish(): void
    {
        $translator = Translator::load(dirname(__DIR__, 2), 'xx');

        self::assertSame('en', $translator->locale);
        self::assertSame(
            'Sign in',
            $translator->get('auth.login.title')
        );
    }

    public function testUnknownTranslationReturnsKey(): void
    {
        $translator = Translator::load(dirname(__DIR__, 2), 'en');

        self::assertSame(
            'missing.translation',
            $translator->get('missing.translation')
        );
    }
}
