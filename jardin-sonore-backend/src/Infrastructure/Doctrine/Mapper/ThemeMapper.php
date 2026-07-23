<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\ContentCatalog\Theme;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;

final readonly class ThemeMapper
{
    public function toDomain(ThemeEntity $themeEntity): Theme
    {
        return new Theme($themeEntity->getLabel(), $themeEntity->getColor(), $themeEntity->getUuid(), $themeEntity->getId());
    }

    public function toEntity(Theme $theme, ?ThemeEntity $themeEntity = null): ThemeEntity
    {
        $themeEntity ??= new ThemeEntity();

        return $themeEntity->setUuid($theme->getUuid())->setLabel($theme->getLabel())->setColor($theme->getColor());
    }
}
