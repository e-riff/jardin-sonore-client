<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Application\Portal\OrganizationAccessContactCreator;
use App\Application\Portal\OrganizationAccessEmailResolver;
use App\Application\Portal\OrganizationAccessEmailSelection;
use App\Application\Portal\PortalAccountMailSenderInterface;
use App\Application\Portal\PortalImpersonationLaunchManager;
use App\Application\Portal\PortalPasswordTokenManager;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use InvalidArgumentException;
use LogicException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

/** @extends AbstractCrudController<UserEntity> */
final class UserCrudController extends AbstractCrudController
{
    /** @var array<string, list<int>> */
    private array $organizationIdsByEmailAddress = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailContactDoctrineRepository $emailContactDoctrineRepository,
        private readonly OrganizationAccessContactCreator $organizationAccessContactCreator,
        private readonly OrganizationAccessEmailResolver $organizationAccessEmailResolver,
        private readonly PortalPasswordTokenManager $portalPasswordTokenManager,
        private readonly PortalAccountMailSenderInterface $portalAccountMailSender,
        private readonly PortalImpersonationLaunchManager $portalImpersonationLaunchManager,
        #[Autowire('%app.portal.public_base_url%')]
        private readonly string $portalPublicBaseUrl,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return UserEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Compte portail')->setEntityLabelInPlural('Comptes portail')->setDefaultSort(['email' => 'ASC'])->setSearchFields(['email']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $sendInvitation = Action::new('sendInvitation', 'Envoyer l’invitation', 'fa fa-envelope')->linkToCrudAction('sendInvitation')->displayIf(static fn (UserEntity $userEntity): bool => UserStatus::PENDING === $userEntity->getStatus());
        $sendPasswordReset = Action::new('sendPasswordReset', 'Réinitialiser le mot de passe', 'fa fa-key')->linkToCrudAction('sendPasswordReset')->displayIf(static fn (UserEntity $userEntity): bool => UserStatus::ACTIVE === $userEntity->getStatus());
        $startImpersonation = Action::new('startImpersonation', 'Ouvrir le portail', 'fa fa-user-secret')
            ->linkToUrl(fn (UserEntity $userEntity): string => $this->generateUrl('admin_user_impersonation_launch', ['id' => $userEntity->getId()]))
            ->renderAsForm()
            ->setTemplatePath('admin/action/portal_impersonation.html.twig')
            ->displayIf(static fn (UserEntity $userEntity): bool => UserStatus::ACTIVE === $userEntity->getStatus());

        return $actions->update(
            Crud::PAGE_INDEX,
            Action::NEW,
            static fn (Action $action): Action => $action->setLabel('Créer un accès organisation')->setIcon('fa fa-user-plus'),
        )->add(Crud::PAGE_INDEX, $sendInvitation)->add(Crud::PAGE_DETAIL, $sendInvitation)->add(Crud::PAGE_EDIT, $sendInvitation)->add(Crud::PAGE_INDEX, $sendPasswordReset)->add(Crud::PAGE_DETAIL, $sendPasswordReset)->add(Crud::PAGE_EDIT, $sendPasswordReset)->add(Crud::PAGE_INDEX, $startImpersonation)->add(Crud::PAGE_DETAIL, $startImpersonation)->add(Crud::PAGE_EDIT, $startImpersonation);
    }

    #[Route('/backoffice/user/{id}/impersonation-launch', name: 'admin_user_impersonation_launch', methods: ['POST'])]
    public function launchImpersonation(Request $request, #[MapEntity(id: 'id')] UserEntity $userEntity): Response
    {
        $adminUserEntity = $this->getUser();
        if (!$adminUserEntity instanceof AdminUserEntity) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->isCsrfTokenValid("portal_impersonation_{$userEntity->getId()}", $request->request->getString('_token'))) {
            throw new AccessDeniedHttpException();
        }

        $issuedPortalImpersonationLaunch = $this->portalImpersonationLaunchManager->issue($userEntity, $adminUserEntity);

        return $this->render('portal_impersonation/launch.html.twig', [
            'portalImpersonationUrl' => rtrim($this->portalPublicBaseUrl, '/') . '/portail/impersonation',
            'rawLaunchToken' => $issuedPortalImpersonationLaunch->rawToken,
        ]);
    }

    /** @param AdminContext<UserEntity> $context */
    #[AdminRoute(path: '/send-invitation', name: 'send_invitation')]
    public function sendInvitation(AdminContext $context): Response
    {
        $userEntity = $context->getEntity()->getInstance();
        if ($userEntity instanceof UserEntity) {
            $this->issueAndSendInvitation($userEntity, 'Invitation envoyée.');
        }

        return $this->redirect($context->getRequest()->headers->get('referer') ?? $this->generateUrl('admin'));
    }

    /** @param AdminContext<UserEntity> $context */
    #[AdminRoute(path: '/send-password-reset', name: 'send_password_reset')]
    public function sendPasswordReset(AdminContext $context): Response
    {
        $userEntity = $context->getEntity()->getInstance();
        if ($userEntity instanceof UserEntity) {
            $issuedPortalPasswordToken = $this->portalPasswordTokenManager->issuePasswordReset($userEntity);

            try {
                $this->portalAccountMailSender->sendPasswordReset($userEntity, $issuedPortalPasswordToken->rawToken);
                $this->addFlash('success', 'Lien de réinitialisation envoyé.');
            } catch (TransportExceptionInterface) {
                $this->addFlash('danger', 'Le lien a été généré, mais l’e-mail n’a pas pu être envoyé. Vous pouvez réessayer.');
            }
        }

        return $this->redirect($context->getRequest()->headers->get('referer') ?? $this->generateUrl('admin'));
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addAssetMapperEntry('app');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield TextField::new('uuid')->onlyOnDetail();
        yield TextField::new('organizationIdForNewAccess', 'Structure')
            ->setFormTypeOption('attr', [
                'data-controller' => 'organization-autocomplete',
                'data-organization-autocomplete-url-value' => $this->generateUrl('admin_portal_organization_autocomplete'),
                'data-portal-access-email-target' => 'organization',
                'placeholder' => 'Rechercher une structure',
            ])
            ->onlyWhenCreating();
        yield ChoiceField::new('linkedEmailAddressForNewAccess', 'E-mail associé')
            ->renderAsNativeWidget()
            ->setFormTypeOption('choice_attr', fn (string $emailAddress): array => [
                'data-organization-ids' => implode(',', $this->organizationIdsByEmailAddress[$emailAddress] ?? []),
            ])
            ->setFormTypeOption('choices', $this->emailChoices())
            ->setFormTypeOption('placeholder', 'Choisir une adresse e-mail existante')
            ->setFormTypeOption('attr', [
                'data-controller' => 'portal-access-email',
                'data-portal-access-email-target' => 'linked-email',
            ])
            ->onlyWhenCreating();
        yield EmailField::new('email', 'E-mail')
            ->setFormTypeOption('attr', ['data-portal-access-email-target' => 'email'])
            ->onlyWhenCreating();
        yield ChoiceField::new('newAccessContactType', 'Ajouter cet e-mail à')
            ->setChoices([
                'La structure' => UserEntity::NEW_ACCESS_CONTACT_ORGANIZATION,
                'Une nouvelle personne' => UserEntity::NEW_ACCESS_CONTACT_PERSON,
            ])
            ->setFormTypeOption('attr', ['data-portal-access-email-target' => 'contact-type'])
            ->onlyWhenCreating();
        yield TextField::new('newPersonFirstNameForNewAccess', 'Prénom de la personne')
            ->setFormTypeOption('attr', ['data-portal-access-email-target' => 'person-first-name'])
            ->onlyWhenCreating();
        yield TextField::new('newPersonLastNameForNewAccess', 'Nom de la personne')
            ->setFormTypeOption('attr', ['data-portal-access-email-target' => 'person-last-name'])
            ->onlyWhenCreating();
        yield EmailField::new('email', 'E-mail')->onlyOnIndex();
        yield TextField::new('firstName', 'Prénom')->hideWhenCreating();
        yield TextField::new('lastName', 'Nom')->hideWhenCreating();
        yield BooleanField::new('newSessionNotificationsEnabled', 'Recevoir les nouvelles séances par e-mail')->hideWhenCreating();
        yield ImageField::new('avatarPath', 'Photo de profil')
            ->setBasePath('/uploads/portal/avatars')
            ->setUploadDir('public/uploads/portal/avatars')
            ->setUploadedFileNamePattern('[uuid].[extension]')
            ->mimeTypes('image/jpeg,image/png,image/webp')
            ->maxSize('2M')
            ->hideWhenCreating()
            ->hideOnIndex();
        yield TextField::new('organizationAccessSummary', 'Structure')
            ->onlyOnIndex()
            ->formatValue(static function (mixed $value, ?UserEntity $userEntity): string {
                if (!$userEntity instanceof UserEntity) {
                    return '';
                }

                $organizationLabels = [];
                foreach ($userEntity->getOrganizationAccesses() as $userOrganizationAccessEntity) {
                    if (!$userOrganizationAccessEntity->isActive()) {
                        continue;
                    }

                    $organizationEntity = $userOrganizationAccessEntity->getOrganization();
                    $municipality = $organizationEntity->getMunicipalitySummary();
                    $organizationLabels[] = '—' === $municipality
                        ? htmlspecialchars($organizationEntity->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        : htmlspecialchars($organizationEntity->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                            . ' <em class="text-muted">' . htmlspecialchars($municipality, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</em>';
                }

                return '' === implode(', ', $organizationLabels) ? '—' : implode(', ', $organizationLabels);
            })
            ->renderAsHtml();
        yield ChoiceField::new('status', 'État')
            ->setChoices(['En attente' => UserStatus::PENDING, 'Actif' => UserStatus::ACTIVE, 'Inactif' => UserStatus::INACTIVE])
            ->hideOnForm();
        yield BooleanField::new('active', 'Actif')->hideOnForm();
        yield CollectionField::new('organizationAccesses', 'Accès aux structures')
            ->useEntryCrudForm(UserOrganizationAccessCrudController::class)
            ->allowAdd()
            ->onlyWhenUpdating();
    }

    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if (!$entityInstance instanceof UserEntity) {
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        $organizationId = $entityInstance->getOrganizationIdForNewAccess();
        $organizationEntity = null === $organizationId || !ctype_digit($organizationId)
            ? null
            : $entityManager->find(OrganizationEntity::class, (int) $organizationId);

        if (!$organizationEntity instanceof OrganizationEntity) {
            throw new LogicException('A portal account must be created from an organization.');
        }

        $organizationAccessEmailSelection = $this->resolveOrCreateContact($entityInstance, $organizationEntity);
        $entityInstance->setEmail($organizationAccessEmailSelection->emailAddress);
        $entityInstance->addOrganizationAccess(
            (new UserOrganizationAccessEntity())
                ->setOrganization($organizationEntity)
                ->setPerson($organizationAccessEmailSelection->personEntity),
        );

        parent::persistEntity($entityManager, $entityInstance);
        $this->issueAndSendInvitation($entityInstance, 'Compte créé et invitation envoyée.');
    }

    #[Route('/backoffice/portal-organizations/autocomplete', name: 'admin_portal_organization_autocomplete', methods: ['GET'])]
    public function autocompleteOrganization(Request $request): JsonResponse
    {
        $query = trim($request->query->getString('q'));
        $queryBuilder = $this->entityManager->getRepository(OrganizationEntity::class)
            ->createQueryBuilder('organization')
            ->leftJoin('organization.contactDetails', 'contactDetails')->addSelect('contactDetails')
            ->leftJoin('contactDetails.addressContacts', 'addressContact')->addSelect('addressContact')
            ->leftJoin('addressContact.municipality', 'municipality')->addSelect('municipality')
            ->orderBy('organization.name', 'ASC')
            ->setMaxResults(25);
        if ('' !== $query) {
            $queryBuilder->andWhere('LOWER(organization.name) LIKE LOWER(:query)')->setParameter('query', '%' . $query . '%');
        }

        return new JsonResponse(['items' => array_map(static function (OrganizationEntity $organizationEntity): array {
            $municipality = $organizationEntity->getMunicipalitySummary();

            return ['id' => (string) $organizationEntity->getId(), 'label' => '—' === $municipality ? $organizationEntity->getName() : "{$organizationEntity->getName()} — {$municipality}"];
        }, $queryBuilder->getQuery()->getResult())]);
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
            $this->addFlash('success', $successMessage);
        } catch (TransportExceptionInterface) {
            $this->addFlash('danger', 'Le lien a été généré, mais l’e-mail n’a pas pu être envoyé. Vous pouvez réessayer.');
        }
    }

    /** @return array<string, string> */
    private function emailChoices(): array
    {
        $emailContactEntities = $this->entityManager->getRepository(EmailContactEntity::class)
            ->createQueryBuilder('emailContact')
            ->leftJoin('emailContact.emailContactLinks', 'emailContactLink')
            ->addSelect('emailContactLink')
            ->leftJoin('emailContactLink.contactDetails', 'contactDetails')
            ->addSelect('contactDetails')
            ->leftJoin('contactDetails.directoryEntry', 'directoryEntry')
            ->addSelect('directoryEntry')
            ->where('emailContact.active = :active')
            ->andWhere('emailContactLink.active = :active')
            ->setParameter('active', true)
            ->orderBy('emailContact.emailAddress', 'ASC')
            ->getQuery()
            ->getResult();

        $emailChoices = [];

        foreach ($emailContactEntities as $emailContactEntity) {
            if (!$emailContactEntity instanceof EmailContactEntity) {
                continue;
            }

            $organizationLabels = [];
            $organizationIds = [];

            foreach ($emailContactEntity->getEmailContactLinks() as $emailContactLinkEntity) {
                if (!$emailContactLinkEntity->isActive()) {
                    continue;
                }

                $directoryEntryEntity = $emailContactLinkEntity->getContactDetails()?->getDirectoryEntry();
                $organizationEntity = $directoryEntryEntity instanceof OrganizationEntity
                    ? $directoryEntryEntity
                    : ($directoryEntryEntity instanceof PersonEntity
                        ? $directoryEntryEntity->getOrganization()
                        : null);

                if (!$organizationEntity instanceof OrganizationEntity || null === $organizationEntity->getId()) {
                    continue;
                }

                $organizationLabels[] = (string) $organizationEntity;
                $organizationIds[] = $organizationEntity->getId();
            }

            $organizationLabels = array_values(array_unique($organizationLabels));
            $organizationIds = array_values(array_unique($organizationIds));

            if ([] === $organizationIds) {
                continue;
            }

            $emailAddress = $emailContactEntity->getEmailAddress();
            $this->organizationIdsByEmailAddress[$emailAddress] = $organizationIds;
            $emailChoices["{$emailAddress} — " . implode(', ', $organizationLabels)] = $emailAddress;
        }

        return $emailChoices;
    }
}
