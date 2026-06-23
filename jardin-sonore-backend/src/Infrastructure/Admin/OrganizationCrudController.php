<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\DirectoryEntryType;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use BackedEnum;
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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<OrganizationEntity>
 */
final class OrganizationCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return OrganizationEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.organization.singular')
            ->setEntityLabelInPlural('address_book.organization.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.organization.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.organization.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.organization.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.organization.page.detail')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name']);
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile(Asset::fromEasyAdminAssetPackage('field-collection.js'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $addPerson = Action::new('addPerson', 'admin.action.add_person', 'fa fa-user-plus')
            ->linkToUrl(fn (OrganizationEntity $organizationEntity): string => $this->generateNewPersonUrl($organizationEntity));
        $addEmail = Action::new('addEmail', 'admin.action.add_email', 'fa fa-envelope')
            ->displayIf(static fn (OrganizationEntity $organizationEntity): bool => null !== $organizationEntity->getContactDetails())
            ->linkToUrl(fn (OrganizationEntity $organizationEntity): string => $this->generateNewContactUrl(EmailContactCrudController::class, $organizationEntity));
        $addPhone = Action::new('addPhone', 'admin.action.add_phone', 'fa fa-phone')
            ->displayIf(static fn (OrganizationEntity $organizationEntity): bool => null !== $organizationEntity->getContactDetails())
            ->linkToUrl(fn (OrganizationEntity $organizationEntity): string => $this->generateNewContactUrl(PhoneContactCrudController::class, $organizationEntity));
        $addAddress = Action::new('addAddress', 'admin.action.add_address', 'fa fa-location-dot')
            ->displayIf(static fn (OrganizationEntity $organizationEntity): bool => null !== $organizationEntity->getContactDetails())
            ->linkToUrl(fn (OrganizationEntity $organizationEntity): string => $this->generateNewContactUrl(AddressContactCrudController::class, $organizationEntity));

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $addPerson)
            ->add(Crud::PAGE_DETAIL, $addEmail)
            ->add(Crud::PAGE_DETAIL, $addPhone)
            ->add(Crud::PAGE_DETAIL, $addAddress);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('type', 'admin.field.organization_type')->setChoices($this->organizationTypeChoices())->setFormTypeOption('value_type_options.translation_domain', 'messages'))
            ->add(ChoiceFilter::new('sector', 'admin.field.organization_sector')->setChoices($this->organizationSectorChoices())->setFormTypeOption('value_type_options.translation_domain', 'messages'))
            ->add(ChoiceFilter::new('customerStatus', 'admin.field.customer_status')->setChoices($this->customerStatusChoices())->setFormTypeOption('value_type_options.translation_domain', 'messages'))
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
        yield TextField::new('name', 'admin.field.name');
        yield ChoiceField::new('type', 'admin.field.organization_type')
            ->setChoices($this->organizationTypeChoices())
            ->formatValue(fn (mixed $value): string => $this->translateEnumValue('address_book.organization_type', $value))
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('placeholder', '');
        yield ChoiceField::new('sector', 'admin.field.organization_sector')
            ->setChoices($this->organizationSectorChoices())
            ->formatValue(fn (mixed $value): string => $this->translateEnumValue('address_book.organization_sector', $value))
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('placeholder', '');
        yield ChoiceField::new('customerStatus', 'admin.field.customer_status')
            ->setChoices($this->customerStatusChoices())
            ->formatValue(fn (mixed $value): string => $this->translateEnumValue('address_book.customer_status', $value))
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('placeholder', '');
        yield AssociationField::new('tags', 'admin.field.tags')->autocomplete();
        yield AssociationField::new('contactDetails', 'admin.field.contact_details')
            ->renderAsEmbeddedForm(ContactDetailsCrudController::class)
            ->setColumns('col-md-12 col-xxl-10')
            ->onlyOnForms();
        yield TextField::new('emailContactsSummary', 'admin.field.email_contacts')
            ->formatValue(static fn (mixed $value): string => nl2br(htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')))
            ->renderAsHtml()
            ->hideOnForm();
        yield TextField::new('phoneContactsSummary', 'admin.field.phone_contacts')
            ->formatValue(static fn (mixed $value): string => nl2br(htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')))
            ->renderAsHtml()
            ->hideOnForm();
        yield TextField::new('addressContactsSummary', 'admin.field.address_contacts')
            ->formatValue(static fn (mixed $value): string => nl2br(htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')))
            ->renderAsHtml()
            ->onlyOnDetail();
        yield AssociationField::new('people', 'admin.field.people')->onlyOnDetail();
        yield AssociationField::new('contactDetails', 'admin.field.contact_details')->onlyOnDetail();
        yield BooleanField::new('active', 'admin.field.active');
    }

    private function generateNewPersonUrl(OrganizationEntity $organizationEntity): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(PersonCrudController::class)
            ->setAction(Action::NEW)
            ->set('organizationId', $organizationEntity->getId())
            ->generateUrl();
    }

    /**
     * @param class-string $crudController
     */
    private function generateNewContactUrl(string $crudController, OrganizationEntity $organizationEntity): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($crudController)
            ->setAction(Action::NEW)
            ->set('contactDetailsId', $organizationEntity->getContactDetails()?->getId())
            ->generateUrl();
    }

    /**
     * @return array<string, OrganizationType>
     */
    private function organizationTypeChoices(): array
    {
        return [
            'address_book.organization_type.creche' => OrganizationType::CRECHE,
            'address_book.organization_type.mairie' => OrganizationType::MAIRIE,
            'address_book.organization_type.ram' => OrganizationType::RAM,
            'address_book.organization_type.mam' => OrganizationType::MAM,
            'address_book.organization_type.mediatheque' => OrganizationType::MEDIATHEQUE,
            'address_book.organization_type.centre' => OrganizationType::CENTRE,
            'address_book.organization_type.garderie' => OrganizationType::GARDERIE,
        ];
    }

    /**
     * @return array<string, OrganizationSector>
     */
    private function organizationSectorChoices(): array
    {
        return [
            'address_book.organization_sector.association' => OrganizationSector::ASSOCIATION,
            'address_book.organization_sector.public' => OrganizationSector::PUBLIC,
            'address_book.organization_sector.private' => OrganizationSector::PRIVATE,
        ];
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
            'address_book.directory_entry_type.organization' => DirectoryEntryType::ORGANIZATION,
        ];
    }

    private function translateEnumValue(string $translationPrefix, mixed $value): string
    {
        return $value instanceof BackedEnum ? $this->translator->trans($translationPrefix . '.' . $value->value) : '';
    }
}
