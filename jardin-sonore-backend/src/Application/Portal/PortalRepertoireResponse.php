<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\MediaResourceEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\RepertoireItemEntity;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;

final readonly class PortalRepertoireResponse
{
    /**
     * @param list<OrganizationEntity>  $organizationEntities
     * @param list<MediaResourceEntity> $mediaEntities
     *
     * @return array<string, mixed>
     */
    public static function fromEntity(
        RepertoireItemEntity $itemEntity,
        array $organizationEntities,
        array $mediaEntities,
        bool $withDetails = false,
        string $mediaBaseUrl = '',
    ): array {
        $organizations = array_map(static fn (OrganizationEntity $organizationEntity): array => [
            'uuid' => $organizationEntity->getUuid()->toRfc4122(),
            'name' => $organizationEntity->getName(),
        ], $organizationEntities);
        usort($organizations, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);
        $themes = array_map(static fn (ThemeEntity $themeEntity): array => [
            'uuid' => $themeEntity->getUuid()->toRfc4122(),
            'label' => $themeEntity->getLabel(),
            'color' => $themeEntity->getColor(),
        ], $itemEntity->getThemes()->toArray());
        $media = array_map(static fn (MediaResourceEntity $mediaEntity): array => [
            'type' => $mediaEntity->getType()->value,
            'title' => $mediaEntity->getTitle(),
            'url' => self::publicMediaUrl($mediaEntity->getPrimaryUrl(), 'resources', $mediaBaseUrl) ?? '',
            'imageUrl' => null === $mediaEntity->getImageUrl() ? null : self::publicMediaUrl($mediaEntity->getImageUrl(), 'images', $mediaBaseUrl),
        ], $mediaEntities);
        $thumbnailUrl = null;
        foreach ($mediaEntities as $mediaEntity) {
            if (null !== $mediaEntity->getImageUrl()) {
                $thumbnailUrl = self::publicMediaUrl($mediaEntity->getImageUrl(), 'images', $mediaBaseUrl);

                if (null !== $thumbnailUrl) {
                    break;
                }
            }

            $videoId = self::youtubeVideoId($mediaEntity->getPrimaryUrl());
            if (null !== $videoId) {
                $thumbnailUrl = "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg";
                break;
            }
        }
        $response = [
            'slug' => $itemEntity->getSlug(),
            'title' => $itemEntity->getTitle(),
            'type' => $itemEntity->getType()->value,
            'updatedAt' => $itemEntity->getUpdatedAt()->format(DATE_ATOM),
            'organizations' => $organizations,
            'themes' => $themes,
            'thumbnailUrl' => $thumbnailUrl,
        ];

        return $withDetails ? $response + [
            'source' => $itemEntity->getSource(),
            'body' => $itemEntity->getBody(),
            'generalInstructions' => $itemEntity->getGeneralInstructions(),
            'contentBlocks' => $itemEntity->getContentBlocks(),
            'notes' => $itemEntity->getNotes(),
            'media' => $media,
        ] : $response;
    }

    private static function publicMediaUrl(string $mediaUrl, string $localDirectory, string $mediaBaseUrl): ?string
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

    private static function youtubeVideoId(string $url): ?string
    {
        $urlParts = parse_url($url);
        if (false === $urlParts || 'https' !== strtolower($urlParts['scheme'] ?? '') || !isset($urlParts['host'])) {
            return null;
        }
        $host = strtolower($urlParts['host']);
        $videoId = match ($host) {
            'youtu.be' => ltrim($urlParts['path'] ?? '', '/'),
            'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'www.youtube-nocookie.com' => self::youtubeVideoIdFromPath($urlParts),
            default => null,
        };

        return is_string($videoId) && 1 === preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) ? $videoId : null;
    }

    /** @param array<string, int|string> $urlParts */
    private static function youtubeVideoIdFromPath(array $urlParts): ?string
    {
        if ('/watch' === ($urlParts['path'] ?? null) && isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParameters);

            return is_string($queryParameters['v'] ?? null) ? $queryParameters['v'] : null;
        }
        $path = $urlParts['path'] ?? '';

        return str_starts_with($path, '/shorts/') || str_starts_with($path, '/embed/') ? explode('/', $path)[2] ?? null : null;
    }
}
