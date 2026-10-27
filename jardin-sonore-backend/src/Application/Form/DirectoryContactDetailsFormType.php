<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactLinkEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<ContactDetailsEntity> */
final class DirectoryContactDetailsFormType extends AbstractType
{
    /** @param FormBuilderInterface<ContactDetailsEntity|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (in_array('email', $options['groups'], true)) {
            $builder->add('emailContactLinks', CollectionType::class, [
                'entry_type' => EmailContactLinkFormType::class,
                'label' => 'admin.field.email_contacts',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'delete_empty' => static fn (mixed $link): bool => $link instanceof EmailContactLinkEntity && $link->isPendingRemoval(),
                'prototype_data' => new EmailContactLinkEntity(),
            ]);
        }

        if (in_array('phone', $options['groups'], true)) {
            $builder->add('phoneContactLinks', CollectionType::class, [
                'entry_type' => PhoneContactLinkFormType::class,
                'label' => 'admin.field.phone_contacts',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'delete_empty' => static fn (mixed $link): bool => $link instanceof PhoneContactLinkEntity && $link->isPendingRemoval(),
                'prototype_data' => new PhoneContactLinkEntity(),
            ]);
        }

        if (in_array('address', $options['groups'], true)) {
            $builder->add('addressContacts', CollectionType::class, [
                'entry_type' => DirectoryAddressContactFormType::class,
                'label' => 'admin.field.address_contacts',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype_data' => new AddressContactEntity(),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContactDetailsEntity::class, 'translation_domain' => 'backoffice', 'groups' => ['email', 'phone', 'address']]);
        $resolver->setAllowedValues('groups', static fn (array $groups): bool => [] === array_diff($groups, ['email', 'phone', 'address']));
    }
}
