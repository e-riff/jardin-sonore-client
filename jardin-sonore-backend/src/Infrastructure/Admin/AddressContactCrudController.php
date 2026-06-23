<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\AddressBook\AddressContactType;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<AddressContactEntity>
 */
final class AddressContactCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AddressContactEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.address_contact.singular')
            ->setEntityLabelInPlural('address_book.address_contact.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.address_contact.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.address_contact.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.address_contact.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.address_contact.page.detail')
            ->setDefaultSort(['city' => 'ASC', 'postalCode' => 'ASC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['address', 'postalCode', 'city', 'label']);
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
            ->add(TextFilter::new('postalCode', 'admin.field.postal_code'))
            ->add(TextFilter::new('city', 'admin.field.city'))
            ->add(EntityFilter::new('municipality', 'admin.field.municipality')->autocomplete())
            ->add(BooleanFilter::new('active', 'admin.field.active'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->onlyOnDetail();
        yield TextField::new('uuid', 'admin.field.uuid')->onlyOnDetail();
        yield AssociationField::new('contactDetails', 'admin.field.contact_details')
            ->setCrudController(ContactDetailsCrudController::class)
            ->autocomplete();
        yield ChoiceField::new('type', 'admin.field.type')
            ->setChoices($this->typeChoices())
            ->formatValue(fn (mixed $value): string => $this->translateEnumValue('address_book.address_contact_type', $value));
        yield TextField::new('label', 'admin.field.label');
        yield TextareaField::new('address', 'admin.field.address')->hideOnIndex();
        yield TextField::new('postalCode', 'admin.field.postal_code');
        yield TextField::new('city', 'admin.field.city');
        yield AssociationField::new('municipality', 'admin.field.municipality')->autocomplete()->hideOnIndex();
        yield BooleanField::new('active', 'admin.field.active');
    }

    public function createEntity(string $entityFqcn): object
    {
        if (AddressContactEntity::class !== $entityFqcn) {
            return parent::createEntity($entityFqcn);
        }

        $addressContactEntity = new AddressContactEntity();
        $contactDetailsEntity = $this->findRequestedContactDetails();

        if ($contactDetailsEntity instanceof ContactDetailsEntity) {
            $addressContactEntity->setContactDetails($contactDetailsEntity);
        }

        return $addressContactEntity;
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
     * @return array<string, AddressContactType>
     */
    private function typeChoices(): array
    {
        return [
            'address_book.address_contact_type.main' => AddressContactType::MAIN,
            'address_book.address_contact_type.office' => AddressContactType::OFFICE,
            'address_book.address_contact_type.billing' => AddressContactType::BILLING,
            'address_book.address_contact_type.delivery' => AddressContactType::DELIVERY,
            'address_book.address_contact_type.home' => AddressContactType::HOME,
            'address_book.address_contact_type.other' => AddressContactType::OTHER,
        ];
    }

    private function translateEnumValue(string $translationPrefix, mixed $value): string
    {
        return $value instanceof BackedEnum ? $this->translator->trans($translationPrefix . '.' . $value->value) : '';
    }
}
