<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\AddressBook\PhoneContactType;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<PhoneContactEntity>
 */
final class PhoneContactCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

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
            ->setSearchFields(['phoneNumber', 'label']);
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
            ->add(BooleanFilter::new('active', 'admin.field.active'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->onlyOnDetail();
        yield TextField::new('uuid', 'admin.field.uuid')->onlyOnDetail();
        yield TelephoneField::new('phoneNumber', 'admin.field.phone_number');
        yield TextField::new('label', 'admin.field.label');
        yield AssociationField::new('contactDetails', 'admin.field.contact_details')
            ->setCrudController(ContactDetailsCrudController::class)
            ->autocomplete();
        yield ChoiceField::new('type', 'admin.field.type')
            ->setChoices($this->typeChoices())
            ->formatValue(fn (mixed $value): string => $this->translateEnumValue('address_book.phone_contact_type', $value));
        yield BooleanField::new('active', 'admin.field.active');
    }

    public function createEntity(string $entityFqcn): object
    {
        if (PhoneContactEntity::class !== $entityFqcn) {
            return parent::createEntity($entityFqcn);
        }

        $phoneContactEntity = new PhoneContactEntity();
        $contactDetailsEntity = $this->findRequestedContactDetails();

        if ($contactDetailsEntity instanceof ContactDetailsEntity) {
            $phoneContactEntity->setContactDetails($contactDetailsEntity);
        }

        return $phoneContactEntity;
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
     * @return array<string, PhoneContactType>
     */
    private function typeChoices(): array
    {
        return [
            'address_book.phone_contact_type.main' => PhoneContactType::MAIN,
            'address_book.phone_contact_type.mobile' => PhoneContactType::MOBILE,
            'address_book.phone_contact_type.office' => PhoneContactType::OFFICE,
            'address_book.phone_contact_type.home' => PhoneContactType::HOME,
            'address_book.phone_contact_type.other' => PhoneContactType::OTHER,
        ];
    }

    private function translateEnumValue(string $translationPrefix, mixed $value): string
    {
        return $value instanceof BackedEnum ? $this->translator->trans($translationPrefix . '.' . $value->value) : '';
    }
}
