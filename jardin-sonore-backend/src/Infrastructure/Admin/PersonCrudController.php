<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Doctrine\Entity\PersonEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<PersonEntity>
 */
final class PersonCrudController extends AbstractCrudController
{
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
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.person.page.detail');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield TextField::new('firstName', 'admin.field.first_name');
        yield TextField::new('lastName', 'admin.field.last_name');
        yield TextField::new('role', 'admin.field.role');
        yield AssociationField::new('organization', 'admin.field.organization');
        yield AssociationField::new('emailContacts', 'admin.field.email_contacts')->onlyOnDetail();
        yield AssociationField::new('phoneContacts', 'admin.field.phone_contacts')->onlyOnDetail();
        yield BooleanField::new('active', 'admin.field.active');
    }
}
