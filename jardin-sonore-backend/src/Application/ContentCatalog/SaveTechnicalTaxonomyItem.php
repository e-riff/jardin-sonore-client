<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

use App\Domain\Model\ContentCatalog\InstrumentTag;
use App\Domain\Model\ContentCatalog\Theme;
use App\Domain\Repository\InstrumentTagRepositoryInterface;
use App\Domain\Repository\ThemeRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class SaveTechnicalTaxonomyItem
{
    public function __construct(
        private ThemeRepositoryInterface $themeRepository,
        private InstrumentTagRepositoryInterface $instrumentTagRepository,
    ) {
    }

    public function __invoke(string $catalog, ?Uuid $uuid, string $label, string $color): void
    {
        $color = $this->normalizeColor($color);

        if ('themes' === $catalog) {
            $item = null === $uuid ? new Theme($label, $color) : $this->themeRepository->findByUuid($uuid);
            if (!$item instanceof Theme) {
                throw new InvalidArgumentException('Theme cannot be found.');
            }

            $this->assertUniqueThemeLabel($label, $item->getUuid());
            $item->rename($label);
            $item->changeColor($color);
            $this->themeRepository->save($item);

            return;
        }

        if ('instrument-tags' === $catalog) {
            $item = null === $uuid ? new InstrumentTag($label, $color) : $this->instrumentTagRepository->findByUuid($uuid);
            if (!$item instanceof InstrumentTag) {
                throw new InvalidArgumentException('Instrument tag cannot be found.');
            }

            $existingItem = $this->instrumentTagRepository->findByLabel($label);
            if (null !== $existingItem && !$existingItem->getUuid()->equals($item->getUuid())) {
                throw new InvalidArgumentException('An instrument tag with this label already exists.');
            }

            $item->rename($label);
            $item->changeColor($color);
            $this->instrumentTagRepository->save($item);

            return;
        }

        throw new InvalidArgumentException('Unknown taxonomy catalog.');
    }

    private function assertUniqueThemeLabel(string $label, Uuid $uuid): void
    {
        $existingTheme = $this->themeRepository->findByLabel($label);
        if (null !== $existingTheme && !$existingTheme->getUuid()->equals($uuid)) {
            throw new InvalidArgumentException('A theme with this label already exists.');
        }
    }

    private function normalizeColor(string $color): string
    {
        $color = trim($color);

        if (preg_match('/^#([0-9a-f]{6})$/i', $color, $matches)) {
            return '#' . strtolower($matches[1]);
        }

        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $color, $matches)) {
            $channels = array_map('intval', array_slice($matches, 1));

            if (3 === count($channels) && 255 >= max($channels)) {
                return sprintf('#%02x%02x%02x', ...$channels);
            }
        }

        throw new InvalidArgumentException('Taxonomy color must be a hexadecimal or RGB color.');
    }
}
