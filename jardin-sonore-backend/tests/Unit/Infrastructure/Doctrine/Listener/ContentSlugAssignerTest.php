<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Listener;

use App\Application\Slug\SlugGenerator;
use App\Infrastructure\Doctrine\Entity\RepertoireItemEntity;
use App\Infrastructure\Doctrine\Listener\ContentSlugAssigner;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

final class ContentSlugAssignerTest extends TestCase
{
    public function testItAssignsASlugOnlyWhenTheRepertoireItemDoesNotHaveOne(): void
    {
        $repertoireItemEntity = (new RepertoireItemEntity())->setTitle('La pluie');
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['slug' => 'la-pluie'])->willReturn(null);
        $objectManager = $this->createMock(ObjectManager::class);
        $objectManager->expects(self::once())->method('getRepository')->with(RepertoireItemEntity::class)->willReturn($repository);
        $contentSlugAssigner = new ContentSlugAssigner(new SlugGenerator());

        $contentSlugAssigner->assignSlug($repertoireItemEntity, $objectManager);
        $repertoireItemEntity->setTitle('La pluie douce');
        $contentSlugAssigner->assignSlug($repertoireItemEntity, $objectManager);

        self::assertSame('la-pluie', $repertoireItemEntity->getSlug());
    }
}
