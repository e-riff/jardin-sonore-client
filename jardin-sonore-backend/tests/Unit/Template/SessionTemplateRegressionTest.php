<?php

declare(strict_types=1);

namespace App\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

final class SessionTemplateRegressionTest extends TestCase
{
    public function testActivityFormExplicitlyRequestsTurboStreamResponses(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity_form.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("'data-turbo-stream': 'true'", $template);
    }

    public function testPreviewDoesNotRenderBodyWhenItDuplicatesLyrics(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/show.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('{% if sequence.body and sequence.body != sequence.lyrics %}', $template);
    }

    public function testSequenceInstrumentLabelIsTranslated(): void
    {
        $translations = file_get_contents(__DIR__ . '/../../../translations/sessions+intl-icu.fr.yaml');

        self::assertIsString($translations);
        self::assertMatchesRegularExpression('/^      instruments: "Instruments pour cette activité"$/m', $translations);
    }
}
