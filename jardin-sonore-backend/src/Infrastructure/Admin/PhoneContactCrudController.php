<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Admin\Formatter\ContactDisplayFormatter;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

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
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.phone_contact.page.detail')
            ->setDefaultSort(['phoneNumber' => 'ASC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['phoneNumber']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('active', 'admin.field.active'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->onlyOnDetail();
        yield TextField::new('uuid', 'admin.field.uuid')->onlyOnDetail();
        yield TextField::new('phoneNumber', 'admin.field.phone_number')
            ->formatValue(static fn (mixed $value): string => ContactDisplayFormatter::phoneLink($value))
            ->renderAsHtml()
            ->hideOnForm();
        yield TelephoneField::new('phoneNumber', 'admin.field.phone_number')
            ->setHelp('admin.help.phone_number')
            ->onlyOnForms();
        yield TextField::new('linkedDirectoryEntriesSummary', 'admin.field.contact_details')
            ->formatValue(static fn (mixed $value): string => ContactDisplayFormatter::textSummary($value))
            ->renderAsHtml()
            ->hideOnForm();
        yield BooleanField::new('active', 'admin.field.active');
    }
}
