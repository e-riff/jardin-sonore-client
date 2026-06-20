<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\DirectoryEntryType;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<OrganizationEntity>
 */
final class OrganizationCrudController extends AbstractCrudController
{
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
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.organization.page.detail');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield ChoiceField::new('entryType', 'admin.field.entry_type')->setChoices($this->entryTypeChoices())->hideOnForm();
        yield TextField::new('name', 'admin.field.name');
        yield ChoiceField::new('type', 'admin.field.organization_type')->setChoices($this->organizationTypeChoices());
        yield ChoiceField::new('sector', 'admin.field.organization_sector')->setChoices($this->organizationSectorChoices());
        yield ChoiceField::new('customerStatus', 'admin.field.customer_status')->setChoices($this->customerStatusChoices());
        yield AssociationField::new('tags', 'admin.field.tags')->hideOnIndex();
        yield AssociationField::new('people', 'admin.field.people')->onlyOnDetail();
        yield AssociationField::new('contactDetails', 'admin.field.contact_details')->onlyOnDetail();
        yield BooleanField::new('active', 'admin.field.active');
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
            'address_book.organization_type.unknown' => OrganizationType::UNKNOWN,
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
            'address_book.organization_sector.unknown' => OrganizationSector::UNKNOWN,
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
            'address_book.customer_status.unknown' => CustomerStatus::UNKNOWN,
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
}
