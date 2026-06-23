<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Admin\Form\AddressContactFormType;
use App\Infrastructure\Admin\Form\EmailContactFormType;
use App\Infrastructure\Admin\Form\PhoneContactFormType;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
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

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile(Asset::fromEasyAdminAssetPackage('field-collection.js'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->onlyOnDetail();
        yield TextField::new('uuid', 'admin.field.uuid')->onlyOnDetail();
        yield AssociationField::new('directoryEntry', 'admin.field.directory_entry')->hideOnForm();
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
            ->hideOnForm();
        yield CollectionField::new('emailContacts', 'admin.field.email_contacts')
            ->setEntryType(EmailContactFormType::class)
            ->setEntryIsComplex()
            ->setColumns('col-md-12 col-xxl-10')
            ->setFormTypeOption('prototype_data', new EmailContactEntity())
            ->setFormTypeOption('entry_options.empty_data', static fn (): EmailContactEntity => new EmailContactEntity())
            ->allowAdd()
            ->allowDelete()
            ->onlyOnForms();
        yield CollectionField::new('phoneContacts', 'admin.field.phone_contacts')
            ->setEntryType(PhoneContactFormType::class)
            ->setEntryIsComplex()
            ->setColumns('col-md-12 col-xxl-10')
            ->setFormTypeOption('prototype_data', new PhoneContactEntity())
            ->setFormTypeOption('entry_options.empty_data', static fn (): PhoneContactEntity => new PhoneContactEntity())
            ->allowAdd()
            ->allowDelete()
            ->onlyOnForms();
        yield CollectionField::new('addressContacts', 'admin.field.address_contacts')
            ->setEntryType(AddressContactFormType::class)
            ->setEntryIsComplex()
            ->setColumns('col-md-12 col-xxl-10')
            ->setFormTypeOption('prototype_data', new AddressContactEntity())
            ->setFormTypeOption('entry_options.empty_data', static fn (): AddressContactEntity => new AddressContactEntity())
            ->allowAdd()
            ->allowDelete()
            ->onlyOnForms();
        yield AssociationField::new('emailContacts', 'admin.field.email_contacts')->onlyOnDetail();
        yield AssociationField::new('phoneContacts', 'admin.field.phone_contacts')->onlyOnDetail();
        yield AssociationField::new('addressContacts', 'admin.field.address_contacts')->onlyOnDetail();
    }
}
