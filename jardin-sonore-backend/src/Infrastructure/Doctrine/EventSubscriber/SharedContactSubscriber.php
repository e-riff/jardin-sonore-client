<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\EventSubscriber;

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactLinkEntity;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;

final class SharedContactSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [Events::onFlush];
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($this->scheduledEntities($unitOfWork, EmailContactLinkEntity::class) as $emailContactLink) {
            $this->deduplicateEmailContactLink($entityManager, $unitOfWork, $emailContactLink);
        }

        foreach ($this->scheduledEntities($unitOfWork, PhoneContactLinkEntity::class) as $phoneContactLink) {
            $this->deduplicatePhoneContactLink($entityManager, $unitOfWork, $phoneContactLink);
        }

        foreach ($this->managedEntities($unitOfWork, EmailContactEntity::class) as $emailContact) {
            if ($emailContact->getEmailContactLinks()->isEmpty()) {
                $entityManager->remove($emailContact);
                $unitOfWork->computeChangeSet($entityManager->getClassMetadata(EmailContactEntity::class), $emailContact);
            }
        }

        foreach ($this->managedEntities($unitOfWork, PhoneContactEntity::class) as $phoneContact) {
            if ($phoneContact->getPhoneContactLinks()->isEmpty()) {
                $entityManager->remove($phoneContact);
                $unitOfWork->computeChangeSet($entityManager->getClassMetadata(PhoneContactEntity::class), $phoneContact);
            }
        }
    }

    private function deduplicateEmailContactLink(
        EntityManagerInterface $entityManager,
        UnitOfWork $unitOfWork,
        EmailContactLinkEntity $emailContactLink,
    ): void {
        $emailContact = $emailContactLink->getEmailContact();
        $emailAddress = $emailContact?->getEmailAddress();

        if (null === $emailContact || '' === trim($emailAddress)) {
            return;
        }

        $existingEmailContact = $entityManager->getRepository(EmailContactEntity::class)->findOneBy([
            'emailAddress' => mb_strtolower(trim($emailAddress)),
        ]);

        if (!$existingEmailContact instanceof EmailContactEntity || $existingEmailContact === $emailContact) {
            return;
        }

        $entityManager->detach($emailContact);
        $emailContactLink->setEmailContact($existingEmailContact);
        $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata(EmailContactLinkEntity::class), $emailContactLink);
    }

    private function deduplicatePhoneContactLink(
        EntityManagerInterface $entityManager,
        UnitOfWork $unitOfWork,
        PhoneContactLinkEntity $phoneContactLink,
    ): void {
        $phoneContact = $phoneContactLink->getPhoneContact();
        $phoneNumber = $phoneContact?->getPhoneNumber();

        if (null === $phoneContact || '' === trim($phoneNumber)) {
            return;
        }

        $existingPhoneContact = $entityManager->getRepository(PhoneContactEntity::class)->findOneBy([
            'phoneNumber' => trim($phoneNumber),
        ]);

        if (!$existingPhoneContact instanceof PhoneContactEntity || $existingPhoneContact === $phoneContact) {
            return;
        }

        $entityManager->detach($phoneContact);
        $phoneContactLink->setPhoneContact($existingPhoneContact);
        $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata(PhoneContactLinkEntity::class), $phoneContactLink);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return list<T>
     */
    private function scheduledEntities(UnitOfWork $unitOfWork, string $className): array
    {
        return array_values(array_filter([
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
        ], static fn (object $entity): bool => $entity instanceof $className));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return list<T>
     */
    private function managedEntities(UnitOfWork $unitOfWork, string $className): array
    {
        /** @var array<int, T> $entities */
        $entities = $unitOfWork->getIdentityMap()[$className] ?? [];

        return array_values($entities);
    }
}
