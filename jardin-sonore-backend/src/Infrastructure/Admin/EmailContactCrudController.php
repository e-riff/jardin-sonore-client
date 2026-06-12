<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\AddressBook\ContactDataSource;
use App\Domain\Model\AddressBook\EmailContactType;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<EmailContactEntity>
 */
final class EmailContactCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return EmailContactEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.email_contact.singular')
            ->setEntityLabelInPlural('address_book.email_contact.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.email_contact.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.email_contact.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.email_contact.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.email_contact.page.detail');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield EmailField::new('emailAddress', 'admin.field.email_address');
        yield TextField::new('label', 'admin.field.label');
        yield AssociationField::new('contactDetails', 'admin.field.contact_details');
        yield ChoiceField::new('type', 'admin.field.type')->setChoices($this->typeChoices());
        yield ChoiceField::new('source', 'admin.field.source')->setChoices($this->sourceChoices());
        yield BooleanField::new('optInNewsletter', 'admin.field.opt_in_newsletter');
        yield BooleanField::new('active', 'admin.field.active');
    }

    /**
     * @return array<string, ContactDataSource>
     */
    private function sourceChoices(): array
    {
        return [
            'address_book.contact_source.manual' => ContactDataSource::MANUAL,
            'address_book.contact_source.google_sheets' => ContactDataSource::GOOGLE_SHEETS,
            'address_book.contact_source.legacy_import' => ContactDataSource::LEGACY_IMPORT,
            'address_book.contact_source.unknown' => ContactDataSource::UNKNOWN,
        ];
    }

    /**
     * @return array<string, EmailContactType>
     */
    private function typeChoices(): array
    {
        return [
            'address_book.email_contact_type.main' => EmailContactType::MAIN,
            'address_book.email_contact_type.work' => EmailContactType::WORK,
            'address_book.email_contact_type.personal' => EmailContactType::PERSONAL,
            'address_book.email_contact_type.billing' => EmailContactType::BILLING,
            'address_book.email_contact_type.other' => EmailContactType::OTHER,
        ];
    }
}
