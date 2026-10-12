<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\FormattedMailingMainText;
use DOMDocument;
use DOMElement;
use DOMNode;

final readonly class NewsletterRichTextFormatter
{
    private const array ALLOWED_COLORS = [
        '#A64D43' => '#A64D43',
        'RGB(166,77,67)' => '#A64D43',
        '#692019' => '#692019',
        'RGB(105,32,25)' => '#692019',
        '#47664B' => '#47664B',
        'RGB(71,102,75)' => '#47664B',
    ];

    public function format(string $value): FormattedMailingMainText
    {
        $document = new DOMDocument();
        $previousErrorSetting = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div>' . $value . '</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        $root = $document->getElementsByTagName('div')->item(0);
        if (!$root instanceof DOMElement) {
            return new FormattedMailingMainText('', '');
        }

        [$html, $text] = $this->children($root);

        return new FormattedMailingMainText(trim($html), trim($text));
    }

    /** @return array{string, string} */
    private function children(DOMNode $parent): array
    {
        $html = '';
        $text = '';

        foreach ($parent->childNodes as $node) {
            [$childHtml, $childText] = $this->node($node);
            $html .= $childHtml;
            $text .= $childText;
        }

        return [$html, $text];
    }

    /** @return array{string, string} */
    private function node(DOMNode $node): array
    {
        if (XML_TEXT_NODE === $node->nodeType) {
            $text = $node->nodeValue ?? '';

            return [nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_HTML5), false), $text];
        }

        if (!$node instanceof DOMElement) {
            return ['', ''];
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'img', 'object', 'svg'], true)) {
            return ['', ''];
        }

        if ('br' === $tag) {
            return ['<br>', "\n"];
        }

        [$html, $text] = $this->children($node);

        if (in_array($tag, ['strong', 'b'], true)) {
            return ["<strong>{$html}</strong>", $text];
        }

        if (in_array($tag, ['em', 'i'], true)) {
            return ["<em>{$html}</em>", $text];
        }

        if ('span' === $tag) {
            $color = $this->allowedColor($node->getAttribute('style'));
            if (null !== $color) {
                return ["<span style=\"color:{$color};\">{$html}</span>", $text];
            }
        }

        if ('a' === $tag) {
            $url = html_entity_decode($node->getAttribute('href'), ENT_QUOTES | ENT_HTML5);
            $urlParts = parse_url($url);
            if (false !== $urlParts
                && in_array(strtolower($urlParts['scheme'] ?? ''), ['http', 'https'], true)
                && isset($urlParts['host'])
                && !isset($urlParts['user'])
                && !isset($urlParts['pass'])
                && false !== filter_var($url, FILTER_VALIDATE_URL)) {
                $escapedUrl = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5);

                return ["<a href=\"{$escapedUrl}\" style=\"color:#692019;text-decoration:underline;\">{$html}</a>", "{$text} ({$url})"];
            }
        }

        return [$html, $text];
    }

    private function allowedColor(string $style): ?string
    {
        if (1 !== preg_match('/^\s*color\s*:\s*(#[0-9a-f]{6}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))\s*;?\s*$/i', $style, $matches)) {
            return null;
        }

        $normalizedColor = strtoupper(preg_replace('/\s+/', '', $matches[1]) ?? $matches[1]);

        return self::ALLOWED_COLORS[$normalizedColor] ?? null;
    }
}
