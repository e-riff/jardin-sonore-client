<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class CommercialProjectCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('organization', OrganizationAutocompleteType::class, [
                'label' => 'project.create.organization',
                'placeholder' => 'project.create.organization_placeholder',
                'attr' => [
                    'data-commercial-project-contacts-target' => 'organization',
                    'data-action' => 'change->commercial-project-contacts#organizationChanged',
                ],
            ])
            ->add('newOrganizationName', TextType::class, ['label' => 'project.create.new_organization', 'required' => false])
            ->add('person', ChoiceType::class, [
                'label' => 'project.create.person',
                'required' => false,
                'placeholder' => 'project.create.person_placeholder',
                'choices' => $options['people'],
                'choice_label' => static fn (PersonEntity $personEntity): string => (string) $personEntity,
                'choice_value' => static fn (?PersonEntity $personEntity): string => (string) ($personEntity?->getId() ?? ''),
                'attr' => ['data-commercial-project-contacts-target' => 'person'],
            ])
            ->add('newPersonFirstName', TextType::class, ['label' => 'project.create.person_first_name', 'required' => false])
            ->add('newPersonLastName', TextType::class, ['label' => 'project.create.person_last_name', 'required' => false])
            ->add('newPersonRole', TextType::class, ['label' => 'project.create.person_role', 'required' => false])
            ->add('firstMessage', TextareaType::class, ['label' => 'project.create.first_message'])
            ->add('initialStatus', ChoiceType::class, [
                'label' => 'project.create.initial_status',
                'choices' => [
                    'project.status.discussion' => 'discussion',
                    'project.status.confirmed' => 'confirmed',
                ],
            ])
            ->add('addTask', CheckboxType::class, [
                'label' => 'project.create.add_task',
                'required' => false,
                'attr' => [
                    'data-commercial-project-contacts-target' => 'addTask',
                    'data-action' => 'change->commercial-project-contacts#taskChanged',
                ],
            ])
            ->add('taskTitle', TextType::class, ['label' => 'project.create.task_title', 'required' => false])
            ->add('taskDueOn', DateType::class, [
                'label' => 'project.create.task_due_on',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'model_timezone' => 'Europe/Paris',
                'view_timezone' => 'Europe/Paris',
            ])
            ->add('taskDueTime', TimeType::class, [
                'label' => 'project.create.task_due_time',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'H:i',
            ])
            ->add('title', TextType::class, ['label' => 'project.create.title', 'required' => false])
            ->add('submit', SubmitType::class, ['label' => 'project.create.save', 'attr' => ['class' => 'internal-button']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'commercial', 'people' => []]);
        $resolver->setAllowedTypes('people', 'array');
    }
}
