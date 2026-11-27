<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Domain\Model\Mailing\MailingCampaignType;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use Closure;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use DomainException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final readonly class MailingPreferenceManager
{
    public function __construct(private EntityManagerInterface $entityManager, private EmailContactDoctrineRepository $emailContactRepository, private ClockInterface $clock)
    {
    }

    public function refuse(string $emailContactUuid, ?MailingCampaignType $scope): void
    {
        $this->change($emailContactUuid, function (EmailContactEntity $emailContactEntity) use ($scope): void {
            if (MailingCampaignType::OUTREACH !== $scope) {
                $emailContactEntity->setNewsletterRefused(true)->setNewsletterRefusedAt($emailContactEntity->getNewsletterRefusedAt() ?? $this->clock->now());
            }
            if (MailingCampaignType::NEWSLETTER !== $scope) {
                $emailContactEntity->setOutreachRefused(true)->setOutreachRefusedAt($emailContactEntity->getOutreachRefusedAt() ?? $this->clock->now());
            }
            $this->entityManager->getConnection()->executeStatement('UPDATE newsletter_subscription_request SET consumed_at = ? WHERE email_contact_id = ? AND consumed_at IS NULL', [$this->clock->now()->format('Y-m-d H:i:s'), $emailContactEntity->getId()]);
        });
    }

    public function confirmNewsletter(string $emailContactUuid, string $origin): void
    {
        $this->change($emailContactUuid, function (EmailContactEntity $emailContactEntity) use ($origin): void {
            if (!$emailContactEntity->isActive()) {
                throw new DomainException('This newsletter address is blocked.');
            }
            if (!$emailContactEntity->hasFreeNewsletterSubscription() || null === $emailContactEntity->getFreeNewsletterSubscriptionConfirmedAt()) {
                $emailContactEntity->confirmFreeNewsletterSubscription($this->clock->now(), $origin);
            }
            $emailContactEntity->setNewsletterRefused(false)->setNewsletterRefusedAt(null);
            $this->entityManager->getConnection()->executeStatement('UPDATE newsletter_subscription_request SET consumed_at = ? WHERE email_contact_id = ? AND consumed_at IS NULL', [$this->clock->now()->format('Y-m-d H:i:s'), $emailContactEntity->getId()]);
        });
    }

    public function allowDirectoryMailing(string $emailContactUuid): void
    {
        $this->change($emailContactUuid, static function (EmailContactEntity $emailContactEntity): void {
            $emailContactEntity->setDirectoryMailingAllowed(true);
        });
    }

    public function allowOutreach(string $emailContactUuid): void
    {
        $this->change($emailContactUuid, static function (EmailContactEntity $emailContactEntity): void {
            $emailContactEntity->setOutreachRefused(false)->setOutreachRefusedAt(null);
        });
    }

    /** @param Closure(EmailContactEntity): void $operation */
    private function change(string $emailContactUuid, Closure $operation): void
    {
        if (!Uuid::isValid($emailContactUuid)) {
            throw new DomainException('Unknown mailing contact.');
        }
        $this->entityManager->getConnection()->transactional(function () use ($emailContactUuid, $operation): void {
            $emailContactEntity = $this->emailContactRepository->createQueryBuilder('contact')
                ->where('contact.uuid = :uuid')->setParameter('uuid', Uuid::fromString($emailContactUuid), 'uuid')
                ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
            if (!$emailContactEntity instanceof EmailContactEntity) {
                throw new DomainException('Unknown mailing contact.');
            }
            $operation($emailContactEntity);
            $emailContactEntity->refreshHistoricalMailingPreferences();
            $this->entityManager->flush();
        });
    }
}
