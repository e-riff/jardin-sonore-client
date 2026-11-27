<?php

declare(strict_types=1);

namespace App\Infrastructure\Portal;

use App\Domain\Model\Mailing\MailingCampaignType;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Infrastructure\Mailing\MailingPreferenceManager;
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
        private MailingPreferenceManager $mailingPreferenceManager,
    ) {
    }

    public function isEnabled(UserEntity $userEntity): bool
    {
        $emailContactEntity = $this->emailContactRepository->findEntityByEmailAddress($userEntity->getEmail());

        return null !== $emailContactEntity && $emailContactEntity->isActive()
            && !$emailContactEntity->isNewsletterRefused()
            && ($emailContactEntity->isDirectoryMailingAllowed() || ($emailContactEntity->hasFreeNewsletterSubscription() && null !== $emailContactEntity->getFreeNewsletterSubscriptionConfirmedAt()));
    }

    public function hasConfirmedSubscription(UserEntity $userEntity): bool
    {
        $emailContactEntity = $this->emailContactRepository->findEntityByEmailAddress($userEntity->getEmail());

        return null !== $emailContactEntity && $emailContactEntity->hasFreeNewsletterSubscription()
            && null !== $emailContactEntity->getFreeNewsletterSubscriptionConfirmedAt();
    }

    public function isOutreachAllowed(UserEntity $userEntity): bool
    {
        return !($this->emailContactRepository->findEntityByEmailAddress($userEntity->getEmail())?->isOutreachRefused() ?? false);
    }

    public function setOutreachAllowed(UserEntity $userEntity, bool $allowed): void
    {
        if (!$this->entityManager->getConnection()->isTransactionActive()) {
            throw new LogicException('Mailing preferences require the profile transaction.');
        }
        $contact = $this->emailContactRepository->findEntityByEmailAddress($userEntity->getEmail());
        if (null === $contact) {
            if ($allowed) {
                return;
            }
            $contact = (new EmailContactEntity())->setEmailAddress($userEntity->getEmail())->setOptInNewsletter(false);
            $this->entityManager->getConnection()->executeStatement(
                'INSERT INTO email_contact (uuid, email_address, opt_in_newsletter, active, source, unsubscribe_token, created_at, updated_at) VALUES (?, ?, 0, 1, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE email_address = email_address',
                [$contact->getUuid()->toBinary(), $contact->getEmailAddress(), $contact->getSource()?->value, $contact->getUnsubscribeToken(), $contact->getCreatedAt()->format('Y-m-d H:i:s'), $contact->getUpdatedAt()->format('Y-m-d H:i:s')],
                [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
            );
            $contact = $this->emailContactRepository->findEntityByEmailAddress($userEntity->getEmail());
            if (null === $contact) {
                throw new DomainException('Mailing contact could not be created.');
            }
        }
        if ($allowed) {
            $this->mailingPreferenceManager->allowOutreach($contact->getUuid()->toRfc4122());
        } else {
            $this->mailingPreferenceManager->refuse($contact->getUuid()->toRfc4122(), MailingCampaignType::OUTREACH);
        }
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

        $uuid = $emailContactEntity->getUuid()->toRfc4122();
        if ($enabled) {
            $this->mailingPreferenceManager->confirmNewsletter($uuid, 'portal');
        } else {
            $this->mailingPreferenceManager->refuse($uuid, MailingCampaignType::NEWSLETTER);
        }
    }
}
