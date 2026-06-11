<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<PhoneContactEntity>
 */
final class PhoneContactCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PhoneContactEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.phone_contact.singular')
            ->setEntityLabelInPlural('address_book.phone_contact.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.phone_contact.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.phone_contact.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.phone_contact.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.phone_contact.page.detail');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield TelephoneField::new('phoneNumber', 'admin.field.phone_number');
        yield TextField::new('label', 'admin.field.label');
        yield AssociationField::new('organization', 'admin.field.organization');
        yield AssociationField::new('person', 'admin.field.person');
        yield BooleanField::new('active', 'admin.field.active');
    }
}
