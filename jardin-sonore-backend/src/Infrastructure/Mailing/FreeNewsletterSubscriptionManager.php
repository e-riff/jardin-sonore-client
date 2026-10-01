<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\NewsletterConfirmationMailSenderInterface;
use App\Application\Mailing\NewsletterConfirmationState;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\NewsletterSubscriptionRequestEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\NewsletterSubscriptionRequestDoctrineRepository;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use DomainException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final readonly class FreeNewsletterSubscriptionManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailContactDoctrineRepository $emailContactRepository,
        private NewsletterSubscriptionRequestDoctrineRepository $subscriptionRequestRepository,
        private ClockInterface $clock,
        private NewsletterConfirmationMailSenderInterface $confirmationMailSender,
    ) {
    }

    public function requestSubscription(string $emailAddress): void
    {
        $emailAddress = $this->normalizeEmailAddress($emailAddress);
        $rawToken = $this->entityManager->getConnection()->transactional(function () use ($emailAddress): ?string {
            $emailContactEntity = $this->createOrLockContact($emailAddress);
            if (!$emailContactEntity->isActive() || $this->isSubscribed($emailContactEntity)) {
                return null;
            }
            $now = $this->clock->now();
            $subscriptionRequestEntity = $this->subscriptionRequestRepository->findLockedByContact($emailContactEntity);
            if (null !== $subscriptionRequestEntity && $subscriptionRequestEntity->getRequestedAt()->modify('+60 seconds') > $now) {
                return null;
            }
            $rawToken = bin2hex(random_bytes(32));
            if (null === $subscriptionRequestEntity) {
                $subscriptionRequestEntity = new NewsletterSubscriptionRequestEntity($emailContactEntity, hash('sha256', $rawToken), $now, $now->modify('+48 hours'));
                $this->entityManager->persist($subscriptionRequestEntity);
            } else {
                $subscriptionRequestEntity->replace(hash('sha256', $rawToken), $now);
            }
            $this->entityManager->flush();

            return $rawToken;
        });
        // Only send after committing the token; no raw token is queued or persisted.
        if (null !== $rawToken) {
            $this->confirmationMailSender->sendConfirmation($emailAddress, $rawToken);
        }
    }

    public function confirmationState(string $rawToken): NewsletterConfirmationState
    {
        if (!$this->isTokenFormatValid($rawToken)) {
            return NewsletterConfirmationState::UNAVAILABLE;
        }
        $subscriptionRequestEntity = $this->subscriptionRequestRepository->findByTokenHash(hash('sha256', $rawToken));
        if (null === $subscriptionRequestEntity) {
            return NewsletterConfirmationState::UNAVAILABLE;
        }
        $this->entityManager->refresh($subscriptionRequestEntity->getEmailContact());

        return $this->state($subscriptionRequestEntity);
    }

    public function confirm(string $rawToken): NewsletterConfirmationState
    {
        if (!$this->isTokenFormatValid($rawToken)) {
            return NewsletterConfirmationState::UNAVAILABLE;
        }
        $tokenHash = hash('sha256', $rawToken);
        $subscriptionRequestEntity = $this->subscriptionRequestRepository->findByTokenHash($tokenHash);
        if (null === $subscriptionRequestEntity) {
            return NewsletterConfirmationState::UNAVAILABLE;
        }
        $emailAddress = $subscriptionRequestEntity->getEmailContact()->getEmailAddress();

        return $this->entityManager->getConnection()->transactional(function () use ($emailAddress, $tokenHash): NewsletterConfirmationState {
            $emailContactEntity = $this->lockContact($emailAddress);
            if (null === $emailContactEntity) {
                return NewsletterConfirmationState::UNAVAILABLE;
            }
            $subscriptionRequestEntity = $this->subscriptionRequestRepository->findLockedByContact($emailContactEntity);
            if (null === $subscriptionRequestEntity || !hash_equals($subscriptionRequestEntity->getTokenHash(), $tokenHash)) {
                return NewsletterConfirmationState::UNAVAILABLE;
            }
            $state = $this->state($subscriptionRequestEntity);
            if (NewsletterConfirmationState::READY !== $state) {
                return $state;
            }
            $now = $this->clock->now();
            $this->activate($emailContactEntity, $now, $subscriptionRequestEntity->getOrigin());
            $subscriptionRequestEntity->consumeAt($now);
            $this->entityManager->flush();

            return NewsletterConfirmationState::CONFIRMED;
        });
    }

    public function subscribeFromBackoffice(string $emailAddress, bool $consentAttested): EmailContactEntity
    {
        if (!$consentAttested) {
            throw new DomainException('Consent must be attested.');
        }
        $emailAddress = $this->normalizeEmailAddress($emailAddress);

        return $this->entityManager->getConnection()->transactional(function () use ($emailAddress): EmailContactEntity {
            $emailContactEntity = $this->createOrLockContact($emailAddress);
            if (!$emailContactEntity->isActive()) {
                throw new DomainException('This newsletter address is blocked.');
            }
            if (!$this->isSubscribed($emailContactEntity)) {
                $this->activate($emailContactEntity, $this->clock->now(), 'backoffice');
                $this->subscriptionRequestRepository->findLockedByContact($emailContactEntity)?->consumeAt($this->clock->now());
                $this->entityManager->flush();
            }

            return $emailContactEntity;
        });
    }

    public function unsubscribeFromBackoffice(string $emailContactUuid): void
    {
        if (!Uuid::isValid($emailContactUuid)) {
            throw new DomainException('Unknown newsletter contact.');
        }
        $this->entityManager->getConnection()->transactional(function () use ($emailContactUuid): void {
            $emailContactEntity = $this->emailContactRepository->createQueryBuilder('contact')
                ->where('contact.uuid = :uuid')->setParameter('uuid', Uuid::fromString($emailContactUuid), 'uuid')
                ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
            if (!$emailContactEntity instanceof EmailContactEntity) {
                throw new DomainException('Unknown newsletter contact.');
            }
            $emailContactEntity->setOptInNewsletter(false)->setUnsubscribedAt($emailContactEntity->getUnsubscribedAt() ?? $this->clock->now());
            $this->subscriptionRequestRepository->findLockedByContact($emailContactEntity)?->consumeAt($this->clock->now());
            $this->entityManager->flush();
        });
    }

    private function activate(EmailContactEntity $emailContactEntity, DateTimeImmutable $now, string $origin): void
    {
        $emailContactEntity->confirmFreeNewsletterSubscription($now, $origin);
        $emailContactEntity->setOptInNewsletter(true)->setUnsubscribedAt(null);
    }

    private function isSubscribed(EmailContactEntity $emailContactEntity): bool
    {
        return $emailContactEntity->hasFreeNewsletterSubscription() && null !== $emailContactEntity->getFreeNewsletterSubscriptionConfirmedAt()
            && $emailContactEntity->hasOptInNewsletter() && !$emailContactEntity->isUnsubscribed();
    }

    private function state(NewsletterSubscriptionRequestEntity $subscriptionRequestEntity): NewsletterConfirmationState
    {
        if ($subscriptionRequestEntity->isConsumed()) {
            return NewsletterConfirmationState::CONSUMED;
        }

        return $subscriptionRequestEntity->isExpiredAt($this->clock->now()) || !$subscriptionRequestEntity->getEmailContact()->isActive()
            ? NewsletterConfirmationState::UNAVAILABLE : NewsletterConfirmationState::READY;
    }

    private function normalizeEmailAddress(string $emailAddress): string
    {
        $emailAddress = mb_strtolower(trim($emailAddress));
        if (255 < strlen($emailAddress) || false === filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Invalid newsletter address.');
        }

        return $emailAddress;
    }

    private function isTokenFormatValid(string $rawToken): bool
    {
        return 1 === preg_match('/^[a-f0-9]{64}$/D', $rawToken);
    }

    private function createOrLockContact(string $emailAddress): EmailContactEntity
    {
        $newEmailContactEntity = (new EmailContactEntity())->setEmailAddress($emailAddress)->setOptInNewsletter(false);
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO email_contact (uuid, email_address, opt_in_newsletter, active, source, unsubscribe_token, created_at, updated_at) VALUES (?, ?, 0, 1, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE email_address = email_address',
            [$newEmailContactEntity->getUuid()->toBinary(), $emailAddress, $newEmailContactEntity->getSource()?->value, $newEmailContactEntity->getUnsubscribeToken(), $now, $now],
            [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
        );

        return $this->lockContact($emailAddress) ?? throw new DomainException('Newsletter contact could not be created.');
    }

    private function lockContact(string $emailAddress): ?EmailContactEntity
    {
        return $this->emailContactRepository->createQueryBuilder('contact')->where('contact.emailAddress = :email')->setParameter('email', $emailAddress)
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
    }
}
