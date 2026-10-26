<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\UserEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/** @extends AbstractType<UserEntity> */
final class PortalUserType extends AbstractType
{
    /**
     * @param FormBuilderInterface<UserEntity|null> $builder
     * @param array<string, mixed>                  $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['creating']) {
            $builder
                ->add('organizationIdForNewAccess', TextType::class, [
                    'label' => 'technical.portal_user.form.organization',
                    'attr' => [
                        'data-controller' => 'organization-autocomplete',
                        'data-organization-autocomplete-url-value' => $options['organization_autocomplete_url'],
                        'data-portal-access-email-target' => 'organization',
                        'placeholder' => 'technical.portal_user.form.organization_placeholder',
                    ],
                    'help' => 'technical.portal_user.form.one_organization_per_access',
                ])
                ->add('linkedEmailAddressForNewAccess', TextType::class, [
                    'label' => 'technical.portal_user.form.linked_email',
                    'required' => false,
                    'attr' => [
                        'data-controller' => 'portal-access-email',
                        'data-portal-access-email-url-value' => $options['email_autocomplete_url'],
                        'data-portal-access-email-target' => 'linked-email',
                        'placeholder' => 'technical.portal_user.form.linked_email_placeholder',
                    ],
                ])
                ->add('email', EmailType::class, [
                    'label' => 'technical.portal_user.form.email',
                    'attr' => ['data-portal-access-email-target' => 'email'],
                ])
                ->add('newAccessContactType', ChoiceType::class, [
                    'label' => 'technical.portal_user.form.contact_type',
                    'choices' => [
                        'technical.portal_user.form.contact_organization' => UserEntity::NEW_ACCESS_CONTACT_ORGANIZATION,
                        'technical.portal_user.form.contact_person' => UserEntity::NEW_ACCESS_CONTACT_PERSON,
                    ],
                    'attr' => ['data-portal-access-email-target' => 'contact-type'],
                ])
                ->add('newPersonLastNameForNewAccess', TextType::class, [
                    'label' => 'technical.portal_user.form.person_last_name',
                    'required' => false,
                    'attr' => ['data-portal-access-email-target' => 'person-last-name'],
                ])
                ->add('newPersonFirstNameForNewAccess', TextType::class, [
                    'label' => 'technical.portal_user.form.person_first_name',
                    'required' => false,
                    'attr' => ['data-portal-access-email-target' => 'person-first-name'],
                ]);

            return;
        }

        $builder
            ->add('lastName', TextType::class, ['label' => 'technical.portal_user.form.last_name', 'required' => false])
            ->add('firstName', TextType::class, ['label' => 'technical.portal_user.form.first_name', 'required' => false])
            ->add('newSessionNotificationsEnabled', CheckboxType::class, [
                'label' => 'technical.portal_user.form.new_session_notifications',
                'required' => false,
            ])
            ->add('avatar', FileType::class, [
                'label' => 'technical.portal_user.form.avatar',
                'mapped' => false,
                'required' => false,
                'constraints' => [new Image(maxSize: '2M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])],
            ])
            ->add('removeAvatar', CheckboxType::class, [
                'label' => 'technical.portal_user.form.remove_avatar',
                'mapped' => false,
                'required' => false,
            ])
            ->add('organizationAccesses', CollectionType::class, [
                'label' => 'technical.portal_user.form.accesses',
                'entry_type' => UserOrganizationAccessType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'delete_empty' => false,
                'prototype' => true,
                'help' => 'technical.portal_user.form.one_organization_per_access',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserEntity::class,
            'creating' => false,
            'organization_autocomplete_url' => '',
            'email_autocomplete_url' => '',
            'translation_domain' => 'technical',
        ]);
        $resolver->setAllowedTypes('creating', 'bool');
        $resolver->setAllowedTypes('organization_autocomplete_url', 'string');
        $resolver->setAllowedTypes('email_autocomplete_url', 'string');
    }
}
