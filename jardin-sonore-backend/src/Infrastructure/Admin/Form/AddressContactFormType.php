<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Form;

use App\Domain\Model\AddressBook\AddressContactType;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AddressContactEntity>
 */
final class AddressContactFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface<AddressContactEntity|null> $builder
     * @param array<string, mixed>                            $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('address', TextareaType::class, [
                'label' => 'admin.field.address',
                'required' => false,
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'admin.field.postal_code',
                'required' => false,
            ])
            ->add('city', TextType::class, [
                'label' => 'admin.field.city',
                'required' => false,
            ])
            ->add('municipality', MunicipalityAutocompleteType::class, [
                'label' => 'admin.field.municipality',
                'required' => false,
            ])
            ->add('label', TextType::class, [
                'label' => 'admin.field.label',
                'required' => false,
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'admin.field.type',
                'choices' => $this->typeChoices(),
                'choice_translation_domain' => 'backoffice',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'admin.field.active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddressContactEntity::class,
            'translation_domain' => 'backoffice',
        ]);
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
}
