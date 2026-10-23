<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Domain\Model\Session\MediaResource;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MediaResourceChoiceLabels
{
    /**
     * @param list<MediaResource> $mediaResources
     *
     * @return array<string, string> UUID-keyed labels
     */
    public static function byUuid(array $mediaResources, TranslatorInterface $translator): array
    {
        $labelsByUuid = [];

        foreach ($mediaResources as $mediaResource) {
            $mediaUuid = $mediaResource->getUuid()->toRfc4122();
            $typeLabel = $translator->trans($mediaResource->getType()->translationKey(), [], 'sessions');
            $source = trim((string) $mediaResource->getSource());
            $descriptor = '' !== $source ? $source : self::mediaDescriptor($mediaResource->getPrimaryUrl(), $mediaUuid);
            $reference = $mediaResource->getId() ?? substr($mediaUuid, -6);
            $labelsByUuid[$mediaUuid] = "{$reference} — {$mediaResource->getTitle()} — {$typeLabel} — {$descriptor}";
        }

        return $labelsByUuid;
    }

    private static function mediaDescriptor(string $mediaUrl, string $mediaUuid): string
    {
        $urlParts = parse_url($mediaUrl);
        $filename = false === $urlParts ? '' : basename($urlParts['path'] ?? '');

        if ('' === $filename || preg_match('/^[0-9a-f-]{32,36}\.[a-z0-9]+$/i', $filename)) {
            return 'réf. ' . substr($mediaUuid, -6);
        }

        return $filename;
    }
}
