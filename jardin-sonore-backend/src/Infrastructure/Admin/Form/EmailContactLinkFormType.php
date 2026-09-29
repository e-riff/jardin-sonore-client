<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Form;

use App\Domain\Model\AddressBook\EmailContactType;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<EmailContactLinkEntity>
 */
final class EmailContactLinkFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface<EmailContactLinkEntity|null> $builder
     * @param array<string, mixed>                              $options
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
                'choice_translation_domain' => 'backoffice',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'admin.field.link_active',
                'required' => false,
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $submittedData = $event->getData();
            $emailContactLinkEntity = $event->getForm()->getData();
            $submittedValue = is_array($submittedData) ? ($submittedData['emailAddress'] ?? null) : null;

            if (!$emailContactLinkEntity instanceof EmailContactLinkEntity || !is_string($submittedValue) || '' === trim($submittedValue)) {
                return;
            }

            $emailContactEntity = $emailContactLinkEntity->getEmailContact();

            if (
                $emailContactEntity instanceof EmailContactEntity
                && 1 < $emailContactEntity->getEmailContactLinks()->count()
                && mb_strtolower(trim($submittedValue)) !== $emailContactEntity->getEmailAddress()
            ) {
                $emailContactLinkEntity->setEmailContact((new EmailContactEntity())->setOptInNewsletter(false));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EmailContactLinkEntity::class,
            'translation_domain' => 'backoffice',
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
}
