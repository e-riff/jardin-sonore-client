<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\AddressBook\ContactDataSource;
use App\Infrastructure\Admin\Formatter\ContactDisplayFormatter;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use BackedEnum;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<EmailContactEntity>
 */
final class EmailContactCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

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
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.email_contact.page.detail')
            ->setDefaultSort(['emailAddress' => 'ASC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['emailAddress']);
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
            ->add(ChoiceFilter::new('source', 'admin.field.source')->setChoices($this->sourceChoices())->setFormTypeOption('value_type_options.translation_domain', 'backoffice'))
            ->add(BooleanFilter::new('optInNewsletter', 'admin.field.opt_in_newsletter'))
            ->add(DateTimeFilter::new('unsubscribedAt', 'admin.field.unsubscribed_at'))
            ->add(BooleanFilter::new('active', 'admin.field.active'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->onlyOnDetail();
        yield TextField::new('uuid', 'admin.field.uuid')->onlyOnDetail();
        yield TextField::new('emailAddress', 'admin.field.email_address')
            ->formatValue(static fn (mixed $value): string => ContactDisplayFormatter::emailLink($value))
            ->renderAsHtml()
            ->hideOnForm();
        yield EmailField::new('emailAddress', 'admin.field.email_address')->onlyOnForms();
        yield TextField::new('linkedDirectoryEntriesSummary', 'admin.field.contact_details')
            ->formatValue(static fn (mixed $value): string => ContactDisplayFormatter::textSummary($value))
            ->renderAsHtml()
            ->hideOnForm();
        yield ChoiceField::new('source', 'admin.field.source')
            ->setChoices($this->sourceChoices())
            ->formatValue(fn (mixed $value): string => $this->translateEnumValue('address_book.contact_source', $value))
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('placeholder', '');
        yield BooleanField::new('optInNewsletter', 'admin.field.opt_in_newsletter');
        yield DateTimeField::new('unsubscribedAt', 'admin.field.unsubscribed_at')->hideOnForm();
        yield TextField::new('unsubscribeToken', 'admin.field.unsubscribe_token')->onlyOnDetail();
        yield BooleanField::new('active', 'admin.field.shared_active');
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
            'address_book.contact_source.directory_import' => ContactDataSource::DIRECTORY_IMPORT,
        ];
    }

    private function translateEnumValue(string $translationPrefix, mixed $value): string
    {
        return $value instanceof BackedEnum ? $this->translator->trans("{$translationPrefix}.{$value->value}", [], 'backoffice') : '';
    }
}
