<?php

declare(strict_types=1);

namespace App\Application\Portal;

final class PortalMediaUrl
{
    public static function publicUrl(string $mediaUrl, string $localDirectory, string $mediaBaseUrl): ?string
    {
        $urlParts = parse_url($mediaUrl);

        if (false === $urlParts) {
            return null;
        }

        if ('' !== ($urlParts['scheme'] ?? '')) {
            return in_array(strtolower($urlParts['scheme']), ['http', 'https'], true)
                && isset($urlParts['host'])
                && !isset($urlParts['user'])
                && !isset($urlParts['pass']) ? $mediaUrl : null;
        }

        $path = ltrim($urlParts['path'] ?? '', '/');

        if (1 !== preg_match('~^uploads/media/' . preg_quote($localDirectory, '~') . '/[a-z0-9._-]+$~i', $path)
            || str_contains($path, '..')) {
            return null;
        }

        return '' === $mediaBaseUrl ? '/' . $path : rtrim($mediaBaseUrl, '/') . '/' . $path;
    }
}
