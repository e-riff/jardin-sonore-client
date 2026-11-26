<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use DOMDocument;
use DOMElement;
use DOMNode;

final class RecommendationText
{
    public const int MAX_VISIBLE_LENGTH = 420;
    public const int MAX_SOURCE_LENGTH = 20000;

    public static function visibleText(string $value): string
    {
        $document = new DOMDocument();
        $previousErrorSetting = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div>' . $value . '</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }
        $root = $document->getElementsByTagName('div')->item(0);

        return null === $root ? '' : self::nodeText($root);
    }

    public static function trimmedVisibleText(string $value): string
    {
        $visibleText = self::visibleText($value);

        return preg_replace('/^\s+|\s+$/u', '', $visibleText) ?? trim($visibleText);
    }

    private static function nodeText(DOMNode $node): string
    {
        if (XML_TEXT_NODE === $node->nodeType) {
            return $node->nodeValue ?? '';
        }
        if (!$node instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'img', 'object', 'svg'], true)) {
            return '';
        }
        if ('br' === $tag) {
            return "\n";
        }
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= self::nodeText($child);
        }

        return $text;
    }
}
