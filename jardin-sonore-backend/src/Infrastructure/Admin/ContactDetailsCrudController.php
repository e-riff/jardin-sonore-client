<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Admin\Form\AddressContactFormType;
use App\Infrastructure\Admin\Form\EmailContactFormType;
use App\Infrastructure\Admin\Form\PhoneContactFormType;
use App\Infrastructure\Admin\Formatter\ContactDisplayFormatter;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
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
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.contact_details.page.detail')
            ->showEntityActionsInlined()
            ->setSearchFields([
                'uuid',
                'directoryEntry.uuid',
                'emailContacts.emailAddress',
                'emailContacts.label',
                'phoneContacts.phoneNumber',
                'phoneContacts.label',
                'addressContacts.address',
                'addressContacts.postalCode',
                'addressContacts.city',
                'addressContacts.label',
            ]);
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile(Asset::fromEasyAdminAssetPackage('field-collection.js'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $searchQuery = mb_strtolower($searchDto->getQuery());

        if ('' === $searchQuery) {
            return $queryBuilder;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $existingWhere = $queryBuilder->getDQLPart('where');

        $queryBuilder
            ->leftJoin("{$rootAlias}.directoryEntry", 'directoryEntrySearchEntry')
            ->leftJoin(OrganizationEntity::class, 'directoryEntrySearchOrganization', Join::WITH, 'directoryEntrySearchOrganization.id = directoryEntrySearchEntry.id')
            ->leftJoin(PersonEntity::class, 'directoryEntrySearchPerson', Join::WITH, 'directoryEntrySearchPerson.id = directoryEntrySearchEntry.id')
            ->resetDQLPart('where')
            ->andWhere(sprintf(
                '(%s) OR LOWER(directoryEntrySearchOrganization.name) LIKE :directoryEntrySearch OR LOWER(directoryEntrySearchPerson.firstName) LIKE :directoryEntrySearch OR LOWER(directoryEntrySearchPerson.lastName) LIKE :directoryEntrySearch',
                (string) $existingWhere,
            ))
            ->setParameter('directoryEntrySearch', "%{$searchQuery}%");

        return $queryBuilder;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->onlyOnDetail();
        yield TextField::new('uuid', 'admin.field.uuid')->onlyOnDetail();
        yield AssociationField::new('directoryEntry', 'admin.field.directory_entry')->hideOnForm();
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
    }
}
