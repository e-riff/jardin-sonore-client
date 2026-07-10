<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\DirectoryEntryType;
use App\Infrastructure\Admin\Formatter\ContactDisplayFormatter;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Repository\OrganizationDoctrineRepository;
use BackedEnum;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<PersonEntity>
 */
final class PersonCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly OrganizationDoctrineRepository $organizationDoctrineRepository,
        private readonly RequestStack $requestStack,
        private readonly SharedContactLinkResolver $sharedContactLinkResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PersonEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.person.singular')
            ->setEntityLabelInPlural('address_book.person.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.person.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.person.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.person.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.person.page.detail')
            ->setDefaultSort(['lastName' => 'ASC', 'firstName' => 'ASC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['firstName', 'lastName', 'role']);
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile(Asset::fromEasyAdminAssetPackage('field-collection.js'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $addEmail = Action::new('addEmail', 'admin.action.add_email', 'fa fa-envelope')
            ->displayIf(static fn (PersonEntity $personEntity): bool => null !== $personEntity->getContactDetails())
            ->linkToUrl(fn (PersonEntity $personEntity): string => $this->generateNewContactUrl(EmailContactLinkCrudController::class, $personEntity));
        $addPhone = Action::new('addPhone', 'admin.action.add_phone', 'fa fa-phone')
            ->displayIf(static fn (PersonEntity $personEntity): bool => null !== $personEntity->getContactDetails())
            ->linkToUrl(fn (PersonEntity $personEntity): string => $this->generateNewContactUrl(PhoneContactLinkCrudController::class, $personEntity));
        $addAddress = Action::new('addAddress', 'admin.action.add_address', 'fa fa-location-dot')
            ->displayIf(static fn (PersonEntity $personEntity): bool => null !== $personEntity->getContactDetails())
            ->linkToUrl(fn (PersonEntity $personEntity): string => $this->generateNewContactUrl(AddressContactCrudController::class, $personEntity));

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $addEmail)
            ->add(Crud::PAGE_DETAIL, $addPhone)
            ->add(Crud::PAGE_DETAIL, $addAddress);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('organization', 'admin.field.organization')->autocomplete())
            ->add(ChoiceFilter::new('customerStatus', 'admin.field.customer_status')->setChoices($this->customerStatusChoices())->setFormTypeOption('value_type_options.translation_domain', 'backoffice'))
            ->add(BooleanFilter::new('active', 'admin.field.active'))
            ->add(EntityFilter::new('tags', 'admin.field.tags')->canSelectMultiple()->autocomplete());
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->onlyOnDetail();
        yield TextField::new('uuid', 'admin.field.uuid')->onlyOnDetail();
        yield ChoiceField::new('entryType', 'admin.field.entry_type')
            ->setChoices($this->entryTypeChoices())
            ->formatValue(fn (mixed $value): string => $this->translateEnumValue('address_book.directory_entry_type', $value))
            ->hideOnForm();
        yield TextField::new('firstName', 'admin.field.first_name');
        yield TextField::new('lastName', 'admin.field.last_name');
        yield TextField::new('role', 'admin.field.role');
        yield ChoiceField::new('customerStatus', 'admin.field.customer_status')
            ->setChoices($this->customerStatusChoices())
            ->formatValue(fn (mixed $value): string => $this->translateEnumValue('address_book.customer_status', $value))
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('placeholder', '');
        yield AssociationField::new('organization', 'admin.field.organization')->autocomplete();
        yield AssociationField::new('tags', 'admin.field.tags')->autocomplete();
        yield AssociationField::new('contactDetails', 'admin.field.contact_details')
            ->renderAsEmbeddedForm(ContactDetailsCrudController::class)
            ->setColumns('col-md-12 col-xxl-10')
            ->onlyOnForms();
        yield TextField::new('emailContactsSummary', 'admin.field.email_contacts')
            ->formatValue(static fn (mixed $value): string => ContactDisplayFormatter::emailSummary($value))
            ->renderAsHtml()
            ->hideOnForm();
        yield TextField::new('phoneContactsSummary', 'admin.field.phone_contacts')
            ->formatValue(static fn (mixed $value): string => ContactDisplayFormatter::phoneSummary($value))
            ->renderAsHtml()
            ->hideOnForm();
        yield TextField::new('addressContactsSummary', 'admin.field.address_contacts')
            ->formatValue(static fn (mixed $value): string => ContactDisplayFormatter::textSummary($value))
            ->renderAsHtml()
            ->onlyOnDetail();
        yield AssociationField::new('contactDetails', 'admin.field.contact_details')->onlyOnDetail();
        yield BooleanField::new('active', 'admin.field.active');
    }

    public function createEntity(string $entityFqcn): object
    {
        if (PersonEntity::class !== $entityFqcn) {
            return parent::createEntity($entityFqcn);
        }

        $personEntity = new PersonEntity();
        $organizationId = $this->requestStack->getCurrentRequest()?->query->get('organizationId');

        if (is_scalar($organizationId) && ctype_digit((string) $organizationId)) {
            $organizationEntity = $this->organizationDoctrineRepository->findEntityById((int) $organizationId);

            if ($organizationEntity instanceof OrganizationEntity) {
                $personEntity->setOrganization($organizationEntity);
            }
        }

        return $personEntity;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->getContactDetails() instanceof ContactDetailsEntity) {
            $this->sharedContactLinkResolver->resolveContactDetails($entityInstance->getContactDetails());
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->getContactDetails() instanceof ContactDetailsEntity) {
            $this->sharedContactLinkResolver->resolveContactDetails($entityInstance->getContactDetails());
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * @param class-string $crudController
     */
    private function generateNewContactUrl(string $crudController, PersonEntity $personEntity): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($crudController)
            ->setAction(Action::NEW)
            ->set('contactDetailsId', $personEntity->getContactDetails()?->getId())
            ->generateUrl();
    }

    /**
     * @return array<string, CustomerStatus>
     */
    private function customerStatusChoices(): array
    {
        return [
            'address_book.customer_status.customer' => CustomerStatus::CUSTOMER,
            'address_book.customer_status.prospect' => CustomerStatus::PROSPECT,
            'address_book.customer_status.former_customer' => CustomerStatus::FORMER_CUSTOMER,
        ];
    }

    /**
     * @return array<string, DirectoryEntryType>
     */
    private function entryTypeChoices(): array
    {
        return [
            'address_book.directory_entry_type.person' => DirectoryEntryType::PERSON,
        ];
    }

    private function translateEnumValue(string $translationPrefix, mixed $value): string
    {
        return $value instanceof BackedEnum ? $this->translator->trans("{$translationPrefix}.{$value->value}", [], 'backoffice') : '';
    }
}
