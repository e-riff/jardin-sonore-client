<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Directory\OrganizationChoiceLabel;
use App\Application\Form\PortalUserType;
use App\Application\Portal\OrganizationAccessContactCreator;
use App\Application\Portal\OrganizationAccessEmailResolver;
use App\Application\Portal\OrganizationAccessEmailSelection;
use App\Application\Portal\PortalAccountMailSenderInterface;
use App\Application\Portal\PortalImpersonationLaunchManager;
use App\Application\Portal\PortalPasswordTokenManager;
use App\Application\Storage\PortalAvatarStorageInterface;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/technical/portal-users', name: 'technical_portal_user_')]
final class PortalUserManagementController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailContactDoctrineRepository $emailContactDoctrineRepository,
        private readonly OrganizationAccessContactCreator $organizationAccessContactCreator,
        private readonly OrganizationAccessEmailResolver $organizationAccessEmailResolver,
        private readonly PortalPasswordTokenManager $portalPasswordTokenManager,
        private readonly PortalAccountMailSenderInterface $portalAccountMailSender,
        private readonly PortalImpersonationLaunchManager $portalImpersonationLaunchManager,
        private readonly PortalAvatarStorageInterface $portalAvatarStorage,
        private readonly TranslatorInterface $translator,
        #[Autowire('%app.portal.public_base_url%')]
        private readonly string $portalPublicBaseUrl,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('technical/portal_user/index.html.twig');
    }

    #[Route('/organizations/autocomplete', name: 'organization_autocomplete', methods: ['GET'])]
    public function autocompleteOrganization(Request $request): JsonResponse
    {
        $query = trim($request->query->getString('q'));
        $queryBuilder = $this->entityManager->getRepository(OrganizationEntity::class)
            ->createQueryBuilder('organization')
            ->leftJoin('organization.contactDetails', 'contactDetails')->addSelect('contactDetails')
            ->leftJoin('contactDetails.addressContacts', 'addressContact', 'WITH', 'addressContact.active = true')->addSelect('addressContact')
            ->leftJoin('addressContact.municipality', 'municipality')->addSelect('municipality')
            ->orderBy('organization.name', 'ASC')
            ->setMaxResults(25);
        if ('' !== $query) {
            $queryBuilder->andWhere('LOWER(organization.name) LIKE LOWER(:query) OR LOWER(municipality.name) LIKE LOWER(:query) OR LOWER(municipality.postalCode) LIKE LOWER(:query)')->setParameter('query', '%' . addcslashes($query, '%_\\') . '%');
        }

        return new JsonResponse(['items' => array_map(static fn (OrganizationEntity $organizationEntity): array => [
            'id' => (string) $organizationEntity->getId(),
            'label' => OrganizationChoiceLabel::forEntity($organizationEntity),
        ], $queryBuilder->getQuery()->getResult())]);
    }

    #[Route('/linked-email-autocomplete', name: 'linked_email_autocomplete', methods: ['GET'])]
    public function autocompleteLinkedEmail(Request $request): JsonResponse
    {
        $organizationId = $request->query->getInt('organizationId');
        $query = trim($request->query->getString('q'));
        if (1 > $organizationId || 2 > mb_strlen($query)) {
            return new JsonResponse(['items' => []]);
        }

        $queryPattern = '%' . addcslashes($query, '%_\\') . '%';
        $itemsByEmailAddress = [];

        $organizationRows = $this->entityManager->getRepository(OrganizationEntity::class)
            ->createQueryBuilder('organization')
            ->select('emailContact.emailAddress AS emailAddress')
            ->innerJoin('organization.contactDetails', 'contactDetails')
            ->innerJoin('contactDetails.emailContactLinks', 'emailContactLink')
            ->innerJoin('emailContactLink.emailContact', 'emailContact')
            ->where('organization.id = :organizationId')
            ->andWhere('emailContact.active = true')
            ->andWhere('emailContactLink.active = true')
            ->andWhere('LOWER(emailContact.emailAddress) LIKE LOWER(:query)')
            ->setParameter('organizationId', $organizationId)
            ->setParameter('query', $queryPattern)
            ->orderBy('emailContact.emailAddress', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getScalarResult();

        foreach ($organizationRows as $organizationRow) {
            $emailAddress = (string) $organizationRow['emailAddress'];
            $itemsByEmailAddress[$emailAddress] = ['email' => $emailAddress, 'label' => $emailAddress];
        }

        $personRows = $this->entityManager->getRepository(PersonEntity::class)
            ->createQueryBuilder('person')
            ->select('emailContact.emailAddress AS emailAddress, person.firstName AS firstName, person.lastName AS lastName')
            ->innerJoin('person.organization', 'organization')
            ->innerJoin('person.contactDetails', 'contactDetails')
            ->innerJoin('contactDetails.emailContactLinks', 'emailContactLink')
            ->innerJoin('emailContactLink.emailContact', 'emailContact')
            ->where('organization.id = :organizationId')
            ->andWhere('emailContact.active = true')
            ->andWhere('emailContactLink.active = true')
            ->andWhere('LOWER(emailContact.emailAddress) LIKE LOWER(:query)')
            ->setParameter('organizationId', $organizationId)
            ->setParameter('query', $queryPattern)
            ->orderBy('emailContact.emailAddress', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getScalarResult();

        foreach ($personRows as $personRow) {
            $emailAddress = (string) $personRow['emailAddress'];
            $personName = trim((string) $personRow['firstName'] . ' ' . (string) $personRow['lastName']);
            $itemsByEmailAddress[$emailAddress] = [
                'email' => $emailAddress,
                'label' => '' === $personName ? $emailAddress : "{$emailAddress} — {$personName}",
            ];
        }

        return new JsonResponse(['items' => array_slice(array_values($itemsByEmailAddress), 0, 20)]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $userEntity = new UserEntity();
        $form = $this->createForm(PortalUserType::class, $userEntity, [
            'creating' => true,
            'organization_autocomplete_url' => $this->generateUrl('technical_portal_user_organization_autocomplete'),
            'email_autocomplete_url' => $this->generateUrl('technical_portal_user_linked_email_autocomplete'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $organizationId = $userEntity->getOrganizationIdForNewAccess();
                $organizationEntity = null === $organizationId || !ctype_digit($organizationId)
                    ? null
                    : $this->entityManager->find(OrganizationEntity::class, (int) $organizationId);

                if (!$organizationEntity instanceof OrganizationEntity) {
                    throw new LogicException('A portal account must be created from an organization.');
                }

                $organizationAccessEmailSelection = $this->resolveOrCreateContact($userEntity, $organizationEntity);
                $userEntity->setEmail($organizationAccessEmailSelection->emailAddress);
                $userEntity->addOrganizationAccess(
                    (new UserOrganizationAccessEntity())
                        ->setOrganization($organizationEntity)
                        ->setPerson($organizationAccessEmailSelection->personEntity),
                );

                $this->entityManager->persist($userEntity);
                $this->entityManager->flush();
                $this->issueAndSendInvitation($userEntity, 'technical.portal_user.flash.created');

                return $this->redirectToRoute('technical_portal_user_detail', ['id' => $userEntity->getId()], Response::HTTP_SEE_OTHER);
            } catch (InvalidArgumentException $exception) {
                $form->get('email')->addError(new FormError($this->contactErrorMessage($exception)));
            }
        }

        return $this->render('technical/portal_user/new.html.twig', [
            'form' => $form->createView(),
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{id}', name: 'detail', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function detail(#[MapEntity(id: 'id')] UserEntity $userEntity): Response
    {
        return $this->render('technical/portal_user/detail.html.twig', [
            'portalUser' => $userEntity,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(id: 'id')] UserEntity $userEntity): Response
    {
        $form = $this->createForm(PortalUserType::class, $userEntity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatar')->getData();
            if ($avatarFile instanceof UploadedFile) {
                $oldAvatarPath = $userEntity->getAvatarPath();
                $newAvatarPath = $this->portalAvatarStorage->store($avatarFile);
                if (null !== $oldAvatarPath) {
                    $this->portalAvatarStorage->delete($oldAvatarPath);
                }

                $userEntity->setAvatarPath($newAvatarPath);
            } elseif (true === $form->get('removeAvatar')->getData() && null !== $userEntity->getAvatarPath()) {
                $this->portalAvatarStorage->delete($userEntity->getAvatarPath());
                $userEntity->setAvatarPath(null);
            }

            foreach ($userEntity->getOrganizationAccesses() as $userOrganizationAccessEntity) {
                $userOrganizationAccessEntity->setUser($userEntity);
            }

            $this->entityManager->flush();
            $this->addFlash('success', ['message' => 'technical.portal_user.flash.updated', 'domain' => 'technical']);

            return $this->redirectToRoute('technical_portal_user_detail', ['id' => $userEntity->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('technical/portal_user/edit.html.twig', [
            'portalUser' => $userEntity,
            'form' => $form->createView(),
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(id: 'id')] UserEntity $userEntity): Response
    {
        if (!$this->isCsrfTokenValid('technical_portal_user_delete_' . $userEntity->getId(), $request->request->getString('_token'))) {
            throw new AccessDeniedHttpException();
        }

        $this->entityManager->remove($userEntity);
        $this->entityManager->flush();
        $this->addFlash('success', ['message' => 'technical.portal_user.flash.deleted', 'domain' => 'technical']);

        return $this->redirectToRoute('technical_portal_user_index', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/send-invitation', name: 'send_invitation', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function sendInvitation(Request $request, #[MapEntity(id: 'id')] UserEntity $userEntity): Response
    {
        if (UserStatus::PENDING !== $userEntity->getStatus()
            || !$this->isCsrfTokenValid('portal_invitation_' . $userEntity->getId(), $request->request->getString('_token'))) {
            throw new AccessDeniedHttpException();
        }

        $this->issueAndSendInvitation($userEntity, 'technical.portal_user.flash.invitation_sent');

        return $this->redirectToRoute('technical_portal_user_detail', ['id' => $userEntity->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/send-password-reset', name: 'send_password_reset', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function sendPasswordReset(Request $request, #[MapEntity(id: 'id')] UserEntity $userEntity): Response
    {
        if (UserStatus::ACTIVE !== $userEntity->getStatus()
            || !$this->isCsrfTokenValid('portal_password_reset_' . $userEntity->getId(), $request->request->getString('_token'))) {
            throw new AccessDeniedHttpException();
        }

        $issuedPortalPasswordToken = $this->portalPasswordTokenManager->issuePasswordReset($userEntity);

        try {
            $this->portalAccountMailSender->sendPasswordReset($userEntity, $issuedPortalPasswordToken->rawToken);
            $this->addFlash('success', ['message' => 'technical.portal_user.flash.password_reset_sent', 'domain' => 'technical']);
        } catch (TransportExceptionInterface) {
            $this->addFlash('danger', ['message' => 'technical.portal_user.flash.email_failed', 'domain' => 'technical']);
        }

        return $this->redirectToRoute('technical_portal_user_detail', ['id' => $userEntity->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/impersonation-launch', name: 'impersonation_launch', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function launchImpersonation(Request $request, #[MapEntity(id: 'id')] UserEntity $userEntity): Response
    {
        $adminUserEntity = $this->getUser();
        if (!$adminUserEntity instanceof AdminUserEntity
            || !$this->isCsrfTokenValid('portal_impersonation_' . $userEntity->getId(), $request->request->getString('_token'))) {
            throw new AccessDeniedHttpException();
        }

        $issuedPortalImpersonationLaunch = $this->portalImpersonationLaunchManager->issue($userEntity, $adminUserEntity);

        return $this->render('portal_impersonation/launch.html.twig', [
            'portalImpersonationUrl' => rtrim($this->portalPublicBaseUrl, '/') . '/portail/impersonation',
            'rawLaunchToken' => $issuedPortalImpersonationLaunch->rawToken,
        ]);
    }

    private function resolveOrCreateContact(UserEntity $userEntity, OrganizationEntity $organizationEntity): OrganizationAccessEmailSelection
    {
        $linkedEmailAddress = $userEntity->getLinkedEmailAddressForNewAccess();
        if (null !== $linkedEmailAddress) {
            return $this->organizationAccessEmailResolver->resolve($organizationEntity, $linkedEmailAddress);
        }

        try {
            return $this->organizationAccessEmailResolver->resolve($organizationEntity, $userEntity->getEmail());
        } catch (InvalidArgumentException) {
        }

        $emailContactEntity = $this->emailContactDoctrineRepository->findEntityByEmailAddress($userEntity->getEmail());
        if (UserEntity::NEW_ACCESS_CONTACT_ORGANIZATION === $userEntity->getNewAccessContactType()) {
            $this->organizationAccessContactCreator->addOrganizationEmail($organizationEntity, $userEntity->getEmail(), $emailContactEntity);

            return new OrganizationAccessEmailSelection($userEntity->getEmail(), null);
        }

        if (UserEntity::NEW_ACCESS_CONTACT_PERSON === $userEntity->getNewAccessContactType()) {
            $personEntity = $this->organizationAccessContactCreator->addPersonWithEmail(
                $organizationEntity,
                $userEntity->getNewPersonFirstNameForNewAccess() ?? '',
                $userEntity->getNewPersonLastNameForNewAccess() ?? '',
                $userEntity->getEmail(),
                $emailContactEntity,
            );

            return new OrganizationAccessEmailSelection($userEntity->getEmail(), $personEntity);
        }

        throw new LogicException('The new portal access contact type is invalid.');
    }

    private function issueAndSendInvitation(UserEntity $userEntity, string $successMessage): void
    {
        $issuedPortalPasswordToken = $this->portalPasswordTokenManager->issueInvitation($userEntity);
        try {
            $this->portalAccountMailSender->sendInvitation($userEntity, $issuedPortalPasswordToken->rawToken);
            $this->addFlash('success', ['message' => $successMessage, 'domain' => 'technical']);
        } catch (TransportExceptionInterface) {
            $this->addFlash('danger', ['message' => 'technical.portal_user.flash.email_failed', 'domain' => 'technical']);
        }
    }

    private function contactErrorMessage(InvalidArgumentException $exception): string
    {
        return 'The selected email address is not linked to the organization.' === $exception->getMessage()
            ? $this->translator->trans('technical.portal_user.error.email_not_linked', domain: 'technical')
            : $exception->getMessage();
    }
}
