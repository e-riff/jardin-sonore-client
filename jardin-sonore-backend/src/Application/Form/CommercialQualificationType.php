<?php

declare(strict_types=1);

namespace App\Application\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class CommercialQualificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['organization_locked']) {
            $builder
                ->add('organizationMode', ChoiceType::class, [
                    'label' => 'qualification.organization_mode',
                    'choices' => [
                        'qualification.organization_mode_existing' => 'existing',
                        'qualification.organization_mode_new' => 'new',
                    ],
                    'expanded' => true,
                    'multiple' => false,
                    'data' => 'existing',
                ])
                ->add('organization', OrganizationAutocompleteType::class, [
                    'label' => 'qualification.organization',
                    'placeholder' => 'request.select_organization',
                    'required' => false,
                ])
                ->add('newOrganizationName', TextType::class, ['label' => 'qualification.new_organization', 'required' => false]);
        }

        if (!$options['person_locked']) {
            $builder
                ->add('newPersonFirstName', TextType::class, ['label' => 'qualification.person_first_name', 'required' => false])
                ->add('newPersonLastName', TextType::class, ['label' => 'qualification.person_last_name', 'required' => false])
                ->add('newPersonRole', TextType::class, ['label' => 'qualification.person_role', 'required' => false]);
        }

        $builder
            ->add('projectTitle', TextType::class, ['label' => 'qualification.project_title'])
            ->add('submit', SubmitType::class, ['label' => 'qualification.create', 'attr' => ['class' => 'internal-button']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'commercial', 'organization_locked' => false, 'person_locked' => false]);
        $resolver->setAllowedTypes('organization_locked', 'bool');
        $resolver->setAllowedTypes('person_locked', 'bool');
    }
}
