<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\AddressBook\EmailContactType;
use App\Infrastructure\Admin\Formatter\ContactDisplayFormatter;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use BackedEnum;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<EmailContactLinkEntity>
 */
final class EmailContactLinkCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly SharedContactLinkResolver $sharedContactLinkResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return EmailContactLinkEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.email_contact.singular')
            ->setEntityLabelInPlural('address_book.email_contact.plural')
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['emailContact.emailAddress', 'label']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('contactDetails', 'admin.field.contact_details')->autocomplete())
            ->add(\EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter::new('type', 'admin.field.type')->setChoices($this->typeChoices())->setFormTypeOption('value_type_options.translation_domain', 'backoffice'))
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
        yield TextField::new('label', 'admin.field.label');
        yield AssociationField::new('contactDetails', 'admin.field.contact_details')
            ->setCrudController(ContactDetailsCrudController::class)
            ->autocomplete();
        yield ChoiceField::new('type', 'admin.field.type')
            ->setChoices($this->typeChoices())
            ->formatValue(fn (mixed $value): string => $this->translateEnumValue('address_book.email_contact_type', $value));
        yield BooleanField::new('active', 'admin.field.link_active');
    }

    public function createEntity(string $entityFqcn): object
    {
        if (EmailContactLinkEntity::class !== $entityFqcn) {
            return parent::createEntity($entityFqcn);
        }

        $emailContactLink = new EmailContactLinkEntity();
        $contactDetailsEntity = $this->findRequestedContactDetails();

        if ($contactDetailsEntity instanceof ContactDetailsEntity) {
            $emailContactLink->setContactDetails($contactDetailsEntity);
        }

        return $emailContactLink;
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

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->getContactDetails() instanceof ContactDetailsEntity) {
            $this->sharedContactLinkResolver->resolveContactDetails($entityInstance->getContactDetails());
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->getContactDetails() instanceof ContactDetailsEntity) {
            $this->sharedContactLinkResolver->resolveContactDetails($entityInstance->getContactDetails());
        }

        parent::updateEntity($entityManager, $entityInstance);
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
        return $value instanceof BackedEnum ? $this->translator->trans("{$translationPrefix}.{$value->value}", [], 'backoffice') : '';
    }
}
