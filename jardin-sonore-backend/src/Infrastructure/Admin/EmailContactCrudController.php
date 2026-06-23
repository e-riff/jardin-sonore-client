<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\AddressBook\ContactDataSource;
use App\Domain\Model\AddressBook\EmailContactType;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<EmailContactEntity>
 */
final class EmailContactCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
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
            ->setSearchFields(['emailAddress', 'label']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('contactDetails', 'admin.field.contact_details')->autocomplete())
            ->add(ChoiceFilter::new('type', 'admin.field.type')->setChoices($this->typeChoices())->setFormTypeOption('value_type_options.translation_domain', 'messages'))
            ->add(ChoiceFilter::new('source', 'admin.field.source')->setChoices($this->sourceChoices())->setFormTypeOption('value_type_options.translation_domain', 'messages'))
            ->add(BooleanFilter::new('optInNewsletter', 'admin.field.opt_in_newsletter'))
            ->add(DateTimeFilter::new('unsubscribedAt', 'admin.field.unsubscribed_at'))
            ->add(BooleanFilter::new('active', 'admin.field.active'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->onlyOnDetail();
        yield TextField::new('uuid', 'admin.field.uuid')->onlyOnDetail();
        yield EmailField::new('emailAddress', 'admin.field.email_address');
        yield TextField::new('label', 'admin.field.label');
        yield AssociationField::new('contactDetails', 'admin.field.contact_details')
            ->setCrudController(ContactDetailsCrudController::class)
            ->autocomplete();
        yield ChoiceField::new('type', 'admin.field.type')
            ->setChoices($this->typeChoices())
            ->formatValue(fn (mixed $value): string => $this->translateEnumValue('address_book.email_contact_type', $value));
        yield ChoiceField::new('source', 'admin.field.source')
            ->setChoices($this->sourceChoices())
            ->formatValue(fn (mixed $value): string => $this->translateEnumValue('address_book.contact_source', $value))
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('placeholder', '');
        yield BooleanField::new('optInNewsletter', 'admin.field.opt_in_newsletter');
        yield DateTimeField::new('unsubscribedAt', 'admin.field.unsubscribed_at')->hideOnForm();
        yield TextField::new('unsubscribeToken', 'admin.field.unsubscribe_token')->onlyOnDetail();
        yield BooleanField::new('active', 'admin.field.active');
    }

    public function createEntity(string $entityFqcn): object
    {
        if (EmailContactEntity::class !== $entityFqcn) {
            return parent::createEntity($entityFqcn);
        }

        $emailContactEntity = new EmailContactEntity();
        $contactDetailsEntity = $this->findRequestedContactDetails();

        if ($contactDetailsEntity instanceof ContactDetailsEntity) {
            $emailContactEntity->setContactDetails($contactDetailsEntity);
        }

        return $emailContactEntity;
    }

    private function findRequestedContactDetails(): ?ContactDetailsEntity
    {
        $contactDetailsId = $this->requestStack->getCurrentRequest()?->query->get('contactDetailsId');

        if (!is_scalar($contactDetailsId) || !ctype_digit((string) $contactDetailsId)) {
            return null;
        }

        $contactDetailsEntity = $this->entityManager->find(ContactDetailsEntity::class, (int) $contactDetailsId);

        return $contactDetailsEntity instanceof ContactDetailsEntity ? $contactDetailsEntity : null;
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

    private function translateEnumValue(string $translationPrefix, mixed $value): string
    {
        return $value instanceof \BackedEnum ? $this->translator->trans($translationPrefix.'.'.$value->value) : '';
    }
}
