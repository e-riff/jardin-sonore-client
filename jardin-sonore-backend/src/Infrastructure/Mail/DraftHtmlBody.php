<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;

final class DraftHtmlBody
{
    private const array ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'b', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'h2', 'h3', 'a'];

    private const array REMOVED_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'img', 'video', 'audio'];

    public static function sanitize(string $html): string
    {
        if ('' === trim($html) || 20_000 < mb_strlen($html) || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $html)) {
            throw new InvalidArgumentException('Invalid HTML draft body.');
        }

        $document = new DOMDocument();
        $previousErrorSetting = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        $root = $document->getElementsByTagName('body')->item(0);
        if (!$root instanceof DOMElement) {
            throw new InvalidArgumentException('Invalid HTML draft body.');
        }

        $safeHtml = trim(self::children($root));
        if ('' === trim(html_entity_decode(strip_tags($safeHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
            throw new InvalidArgumentException('HTML draft body has no visible text.');
        }

        return $safeHtml;
    }

    private static function children(DOMNode $parent): string
    {
        $html = '';
        foreach ($parent->childNodes as $child) {
            $html .= self::node($child);
        }

        return $html;
    }

    private static function node(DOMNode $node): string
    {
        if (XML_TEXT_NODE === $node->nodeType) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (!$node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, self::REMOVED_TAGS, true)) {
            return '';
        }

        $content = self::children($node);
        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            return $content;
        }
        if ('br' === $tag) {
            return '<br>';
        }
        if ('a' === $tag) {
            $url = html_entity_decode($node->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $urlParts = parse_url($url);
            if (false === $urlParts || !in_array(strtolower($urlParts['scheme'] ?? ''), ['http', 'https'], true)
                || !isset($urlParts['host']) || isset($urlParts['user']) || isset($urlParts['pass'])
                || false === filter_var($url, FILTER_VALIDATE_URL)) {
                return $content;
            }

            return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">' . $content . '</a>';
        }

        return "<{$tag}>{$content}</{$tag}>";
    }
}
