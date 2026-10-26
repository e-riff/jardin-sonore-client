<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

use App\Domain\Repository\InstrumentTagRepositoryInterface;
use App\Domain\Repository\ThemeRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class DeleteTechnicalTaxonomyItem
{
    public function __construct(
        private ThemeRepositoryInterface $themeRepository,
        private InstrumentTagRepositoryInterface $instrumentTagRepository,
    ) {
    }

    public function __invoke(string $catalog, Uuid $uuid): void
    {
        if ('themes' === $catalog) {
            $theme = $this->themeRepository->findByUuid($uuid);
            if (null === $theme) {
                throw new InvalidArgumentException('Theme cannot be found.');
            }
            $this->themeRepository->delete($theme);

            return;
        }

        if ('instrument-tags' === $catalog) {
            $instrumentTag = $this->instrumentTagRepository->findByUuid($uuid);
            if (null === $instrumentTag) {
                throw new InvalidArgumentException('Instrument tag cannot be found.');
            }
            $this->instrumentTagRepository->delete($instrumentTag);

            return;
        }

        throw new InvalidArgumentException('Unknown taxonomy catalog.');
    }
}
