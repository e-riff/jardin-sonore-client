<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<ContactDetailsEntity>
 */
final class ContactDetailsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ContactDetailsEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.contact_details.singular')
            ->setEntityLabelInPlural('address_book.contact_details.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.contact_details.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.contact_details.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.contact_details.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.contact_details.page.detail');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield AssociationField::new('directoryEntry', 'admin.field.directory_entry');
        yield AssociationField::new('emailContacts', 'admin.field.email_contacts')->onlyOnDetail();
        yield AssociationField::new('phoneContacts', 'admin.field.phone_contacts')->onlyOnDetail();
        yield AssociationField::new('addressContacts', 'admin.field.address_contacts')->onlyOnDetail();
    }
}
