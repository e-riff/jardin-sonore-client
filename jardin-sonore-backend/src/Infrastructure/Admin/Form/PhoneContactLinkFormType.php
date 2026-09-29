<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Form;

use App\Domain\Model\AddressBook\PhoneContactType;
use App\Domain\Model\ValueObject\PhoneNumber;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactLinkEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PhoneContactLinkEntity>
 */
final class PhoneContactLinkFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface<PhoneContactLinkEntity|null> $builder
     * @param array<string, mixed>                              $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('phoneNumber', TelType::class, [
                'label' => 'admin.field.phone_number',
                'help' => 'admin.help.phone_number',
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
                'label' => 'admin.field.link_active',
                'required' => false,
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $submittedData = $event->getData();
            $phoneContactLinkEntity = $event->getForm()->getData();
            $submittedValue = is_array($submittedData) ? ($submittedData['phoneNumber'] ?? null) : null;

            if (!$phoneContactLinkEntity instanceof PhoneContactLinkEntity || !is_string($submittedValue) || '' === trim($submittedValue)) {
                return;
            }

            $phoneContactEntity = $phoneContactLinkEntity->getPhoneContact();

            if (
                $phoneContactEntity instanceof PhoneContactEntity
                && 1 < $phoneContactEntity->getPhoneContactLinks()->count()
                && PhoneNumber::normalize($submittedValue) !== $phoneContactEntity->getPhoneNumber()
            ) {
                $phoneContactLinkEntity->setPhoneContact(new PhoneContactEntity());
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PhoneContactLinkEntity::class,
            'translation_domain' => 'backoffice',
        ]);
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
}
