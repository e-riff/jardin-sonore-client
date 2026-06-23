<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Form;

use App\Domain\Model\AddressBook\ContactDataSource;
use App\Domain\Model\AddressBook\EmailContactType;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<EmailContactEntity>
 */
final class EmailContactFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface<EmailContactEntity|null> $builder
     * @param array<string, mixed>                          $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('emailAddress', EmailType::class, [
                'label' => 'admin.field.email_address',
            ])
            ->add('label', TextType::class, [
                'label' => 'admin.field.label',
                'required' => false,
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'admin.field.type',
                'choices' => $this->typeChoices(),
                'choice_translation_domain' => 'messages',
            ])
            ->add('source', ChoiceType::class, [
                'label' => 'admin.field.source',
                'choices' => $this->sourceChoices(),
                'choice_translation_domain' => 'messages',
                'required' => false,
                'placeholder' => '',
            ])
            ->add('optInNewsletter', CheckboxType::class, [
                'label' => 'admin.field.opt_in_newsletter',
                'required' => false,
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'admin.field.active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EmailContactEntity::class,
            'translation_domain' => 'messages',
        ]);
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
}
