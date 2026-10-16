<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Commercial;

use App\Application\Commercial\GoogleCalendarConnectionStoreInterface;
use App\Infrastructure\Doctrine\Entity\GoogleCalendarConnectionEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineGoogleCalendarConnectionStore implements GoogleCalendarConnectionStoreInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function get(): ?GoogleCalendarConnectionEntity
    {
        $entity = $this->entityManager->find(GoogleCalendarConnectionEntity::class, GoogleCalendarConnectionEntity::SINGLETON_ID);

        return $entity instanceof GoogleCalendarConnectionEntity ? $entity : null;
    }

    public function save(GoogleCalendarConnectionEntity $connectionEntity): void
    {
        $this->entityManager->persist($connectionEntity);
        $this->entityManager->flush();
    }

    public function delete(): void
    {
        $connectionEntity = $this->get();
        if (null !== $connectionEntity) {
            $this->entityManager->remove($connectionEntity);
            $this->entityManager->flush();
        }
    }
}
