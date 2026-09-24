<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Listener;

use App\Application\Slug\SlugGenerator;
use App\Infrastructure\Doctrine\Entity\RepertoireItemEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;

#[AsDoctrineListener(event: Events::prePersist, connection: 'default')]
final readonly class ContentSlugAssigner
{
    public function __construct(private SlugGenerator $slugGenerator)
    {
    }

    public function prePersist(PrePersistEventArgs $eventArgs): void
    {
        $this->assignSlug($eventArgs->getObject(), $eventArgs->getObjectManager());
    }

    public function assignSlug(object $entity, ObjectManager $objectManager): void
    {
        if ($entity instanceof RepertoireItemEntity) {
            if ('' !== trim($entity->getSlug())) {
                return;
            }

            $entity->setSlug($this->slugFor($entity->getTitle(), RepertoireItemEntity::class, $objectManager));

            return;
        }

        if ($entity instanceof SessionSummaryEntity && '' === trim($entity->getSlug())) {
            $entity->setSlug($this->slugFor($entity->getTitle(), SessionSummaryEntity::class, $objectManager));
        }
    }

    /** @param class-string $entityClass */
    private function slugFor(string $title, string $entityClass, ObjectManager $objectManager): string
    {
        $repository = $objectManager->getRepository($entityClass);

        return $this->slugGenerator->forTitle(
            $title,
            static fn (string $candidate): bool => null !== $repository->findOneBy(['slug' => $candidate]),
        );
    }
}
