<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/** @see \App\Application\Controller\CommercialProjectController */
final readonly class GrantOrganizationPortalAccess
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailContactDoctrineRepository $emailContactRepository,
        private OrganizationAccessEmailResolver $emailResolver,
        private OrganizationAccessContactCreator $contactCreator,
        private PortalPasswordTokenManager $passwordTokenManager,
        private PortalAccountMailSenderInterface $mailSender,
    ) {
    }

    public function grant(OrganizationEntity $organizationEntity, string $emailAddress, ?PersonEntity $personEntity = null): string
    {
        $emailAddress = mb_strtolower(trim($emailAddress));
        if (!$organizationEntity->isActive() || false === filter_var($emailAddress, FILTER_VALIDATE_EMAIL) || 180 < mb_strlen($emailAddress)) {
            throw new InvalidArgumentException('project.portal_access.invalid');
        }
        $userEntity = $this->entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $emailAddress]);
        $created = !$userEntity instanceof UserEntity;
        if ($userEntity instanceof UserEntity) {
            if (!$userEntity->isActive() || UserStatus::INACTIVE === $userEntity->getStatus()) {
                throw new InvalidArgumentException('project.portal_access.account_disabled');
            }
            foreach ($userEntity->getOrganizationAccesses() as $organizationAccessEntity) {
                if ($organizationAccessEntity->getOrganization() === $organizationEntity) {
                    if (!$organizationAccessEntity->isActive()) {
                        throw new InvalidArgumentException('project.portal_access.access_disabled');
                    }

                    return 'already_granted';
                }
            }
        }

        try {
            $emailSelection = $this->emailResolver->resolve($organizationEntity, $emailAddress, $personEntity);
        } catch (InvalidArgumentException) {
            if (null !== $personEntity) {
                throw new InvalidArgumentException('project.portal_access.invalid');
            }
            $this->contactCreator->addOrganizationEmail($organizationEntity, $emailAddress, $this->emailContactRepository->findEntityByEmailAddress($emailAddress));
            $emailSelection = new OrganizationAccessEmailSelection($emailAddress, null);
        }
        if ($emailSelection->personEntity && !$emailSelection->personEntity->isActive()) {
            throw new InvalidArgumentException('project.portal_access.person_disabled');
        }
        if (!$userEntity instanceof UserEntity) {
            $userEntity = (new UserEntity())->setEmail($emailAddress)
                ->setFirstName($emailSelection->personEntity?->getFirstName())
                ->setLastName($emailSelection->personEntity?->getLastName());
            $this->entityManager->persist($userEntity);
        }
        $userEntity->addOrganizationAccess((new UserOrganizationAccessEntity())->setOrganization($organizationEntity)->setPerson($emailSelection->personEntity));
        $this->entityManager->flush();
        if ($created) {
            $issuedToken = $this->passwordTokenManager->issueInvitation($userEntity);
            $this->mailSender->sendInvitation($userEntity, $issuedToken->rawToken);
        }

        return $created ? 'created' : 'linked';
    }
}
