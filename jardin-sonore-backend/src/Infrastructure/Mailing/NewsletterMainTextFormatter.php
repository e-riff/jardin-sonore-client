<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\FormattedMailingMainText;

final readonly class NewsletterMainTextFormatter
{
    /**
     * @var array<string, string>
     */
    private const array ALLOWED_COLORS = [
        '#A64D43' => '#A64D43',
        '#692019' => '#692019',
        '#47664B' => '#47664B',
    ];

    public function format(string $mainText): FormattedMailingMainText
    {
        $normalizedMainText = $this->normalizeNewlines($mainText);

        return new FormattedMailingMainText(
            html: $this->formatHtml($normalizedMainText),
            text: $this->formatText($normalizedMainText),
        );
    }

    private function formatHtml(string $mainText): string
    {
        $trimmedMainText = trim($mainText);

        if ('' === $trimmedMainText) {
            return '';
        }

        $paragraphs = preg_split('/\n{2,}/', $trimmedMainText) ?: [];
        $formattedParagraphs = [];

        foreach ($paragraphs as $paragraph) {
            $formattedParagraph = trim($paragraph);

            if ('' === $formattedParagraph) {
                continue;
            }

            $sanitizedParagraph = $this->sanitizeInlineMarkup($formattedParagraph);
            $sanitizedParagraph = preg_replace('/\n/', '<br>', $sanitizedParagraph);

            $formattedParagraphs[] = sprintf(
                '<p style="margin:0 0 16px 0;">%s</p>',
                $sanitizedParagraph,
            );
        }

        if ([] === $formattedParagraphs) {
            return '';
        }

        $lastParagraphIndex = array_key_last($formattedParagraphs);

        if (null !== $lastParagraphIndex) {
            $formattedParagraphs[$lastParagraphIndex] = str_replace(
                'margin:0 0 16px 0;',
                'margin:0;',
                $formattedParagraphs[$lastParagraphIndex],
            );
        }

        return implode('', $formattedParagraphs);
    }

    private function formatText(string $mainText): string
    {
        $mainTextWithBreaks = preg_replace('/<br\s*\/?>/i', "\n", $mainText) ?? $mainText;
        $text = strip_tags($mainTextWithBreaks);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function sanitizeInlineMarkup(string $value): string
    {
        $placeholders = [];
        $index = 0;
        $allowedSpanDepth = 0;

        $value = preg_replace_callback(
            '/<\/?(?:strong|em)\s*>|<br\s*\/?>|<span\s+style\s*=\s*"color\s*:\s*#[0-9a-fA-F]{6}\s*"\s*>|<\/span\s*>/i',
            function (array $matches) use (&$placeholders, &$index, &$allowedSpanDepth): string {
                $canonicalTag = $this->canonicalizeAllowedTag($matches[0]);

                if ('' === $canonicalTag) {
                    return '';
                }

                if ('</span>' === $canonicalTag) {
                    if (0 === $allowedSpanDepth) {
                        return '';
                    }

                    --$allowedSpanDepth;
                }

                if (str_starts_with($canonicalTag, '<span ')) {
                    ++$allowedSpanDepth;
                }

                $placeholder = sprintf('__MAIL_MAIN_TEXT_%d__', $index);
                $placeholders[$placeholder] = $canonicalTag;
                ++$index;

                return $placeholder;
            },
            $value,
        ) ?? $value;

        $escapedValue = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);

        return strtr($escapedValue, $placeholders);
    }

    private function canonicalizeAllowedTag(string $tag): string
    {
        $lowerTag = strtolower(trim($tag));

        return match (true) {
            '<strong>' === $lowerTag => '<strong>',
            '</strong>' === $lowerTag => '</strong>',
            '<em>' === $lowerTag => '<em>',
            '</em>' === $lowerTag => '</em>',
            preg_match('/^<br\s*\/?>$/', $lowerTag) === 1 => '<br>',
            '</span>' === $lowerTag => '</span>',
            default => $this->canonicalizeSpanTag($tag),
        };
    }

    private function canonicalizeSpanTag(string $tag): string
    {
        if (preg_match('/^<span\s+style\s*=\s*"color\s*:\s*(#[0-9a-fA-F]{6})\s*"\s*>$/i', trim($tag), $matches) !== 1) {
            return '';
        }

        $color = strtoupper($matches[1]);

        if (!array_key_exists($color, self::ALLOWED_COLORS)) {
            return '';
        }

        return sprintf('<span style="color:%s;">', self::ALLOWED_COLORS[$color]);
    }

    private function normalizeNewlines(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}
