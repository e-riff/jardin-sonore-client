<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class CommercialNoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, ['label' => 'note.content'])
            ->add('person', ChoiceType::class, [
                'label' => 'note.person',
                'required' => false,
                'placeholder' => 'note.no_person',
                'choices' => $options['people'],
                'choice_label' => static fn (PersonEntity $personEntity): string => (string) $personEntity,
                'choice_value' => static fn (?PersonEntity $personEntity): string => (string) ($personEntity?->getId() ?? ''),
            ])
            ->add('nextActionTitle', TextType::class, ['label' => 'note.next_action', 'required' => false])
            ->add('nextActionDueOn', DateType::class, [
                'label' => 'note.due_on',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('submit', SubmitType::class, ['label' => 'note.save', 'attr' => ['class' => 'internal-button']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'commercial', 'people' => []]);
        $resolver->setAllowedTypes('people', 'array');
    }
}
