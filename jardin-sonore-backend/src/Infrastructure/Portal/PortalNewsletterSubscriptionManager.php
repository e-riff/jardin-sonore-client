<?php

declare(strict_types=1);

namespace App\Infrastructure\Portal;

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use DomainException;
use LogicException;

final readonly class PortalNewsletterSubscriptionManager
{
    public function __construct(
        private EmailContactDoctrineRepository $emailContactRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function isEnabled(UserEntity $userEntity): bool
    {
        $emailContactEntity = $this->emailContactRepository->findEntityByEmailAddress($userEntity->getEmail());

        return null !== $emailContactEntity && $emailContactEntity->isActive()
            && $emailContactEntity->hasOptInNewsletter() && !$emailContactEntity->isUnsubscribed();
    }

    public function setEnabled(UserEntity $userEntity, bool $enabled): void
    {
        $connection = $this->entityManager->getConnection();
        if (!$connection->isTransactionActive()) {
            throw new LogicException('Newsletter preferences must be saved inside the profile transaction.');
        }

        $emailAddress = mb_strtolower(trim($userEntity->getEmail()));
        if ($enabled) {
            $newEmailContactEntity = (new EmailContactEntity())->setEmailAddress($emailAddress);
            // The unique address resolves concurrent creations without closing the ORM manager.
            $connection->executeStatement(
                'INSERT INTO email_contact (uuid, email_address, opt_in_newsletter, active, source, unsubscribe_token, created_at, updated_at) VALUES (?, ?, 0, 1, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE email_address = email_address',
                [$newEmailContactEntity->getUuid()->toBinary(), $emailAddress, $newEmailContactEntity->getSource()?->value,
                    $newEmailContactEntity->getUnsubscribeToken(), $newEmailContactEntity->getCreatedAt()->format('Y-m-d H:i:s'),
                    $newEmailContactEntity->getUpdatedAt()->format('Y-m-d H:i:s')],
                [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
            );
        }

        $emailContactEntity = $this->emailContactRepository->createQueryBuilder('emailContact')
            ->where('emailContact.emailAddress = :email')->setParameter('email', $emailAddress)
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();
        if (!$emailContactEntity instanceof EmailContactEntity) {
            return;
        }
        if ($enabled && !$emailContactEntity->isActive()) {
            throw new DomainException('This newsletter address is blocked.');
        }

        if (!$enabled) {
            // The profile transaction already holds the contact lock used by confirmation.
            $connection->executeStatement('UPDATE newsletter_subscription_request SET consumed_at = ? WHERE email_contact_id = ? AND consumed_at IS NULL',
                [(new DateTimeImmutable())->format('Y-m-d H:i:s'), $emailContactEntity->getId()]);
        }

        $emailContactEntity->setOptInNewsletter($enabled)
            ->setUnsubscribedAt($enabled ? null : ($emailContactEntity->getUnsubscribedAt() ?? new DateTimeImmutable()));
    }
}
