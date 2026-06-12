<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\AddressBook\AddressContactType;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<AddressContactEntity>
 */
final class AddressContactCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AddressContactEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.address_contact.singular')
            ->setEntityLabelInPlural('address_book.address_contact.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.address_contact.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.address_contact.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.address_contact.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.address_contact.page.detail');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield AssociationField::new('contactDetails', 'admin.field.contact_details');
        yield ChoiceField::new('type', 'admin.field.type')->setChoices($this->typeChoices());
        yield TextField::new('label', 'admin.field.label');
        yield TextareaField::new('address', 'admin.field.address')->hideOnIndex();
        yield TextField::new('postalCode', 'admin.field.postal_code');
        yield TextField::new('city', 'admin.field.city');
        yield AssociationField::new('municipality', 'admin.field.municipality')->hideOnIndex();
        yield BooleanField::new('active', 'admin.field.active');
    }

    /**
     * @return array<string, AddressContactType>
     */
    private function typeChoices(): array
    {
        return [
            'address_book.address_contact_type.main' => AddressContactType::MAIN,
            'address_book.address_contact_type.office' => AddressContactType::OFFICE,
            'address_book.address_contact_type.billing' => AddressContactType::BILLING,
            'address_book.address_contact_type.delivery' => AddressContactType::DELIVERY,
            'address_book.address_contact_type.home' => AddressContactType::HOME,
            'address_book.address_contact_type.other' => AddressContactType::OTHER,
        ];
    }
}
