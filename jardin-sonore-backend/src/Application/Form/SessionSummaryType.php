<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\SessionSummaryFormModel;
use App\Domain\Repository\InstrumentRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SessionSummaryFormModel>
 */
final class SessionSummaryType extends AbstractType
{
    public function __construct(private readonly InstrumentRepositoryInterface $instrumentRepository)
    {
    }

    /**
     * @param FormBuilderInterface<SessionSummaryFormModel|null> $builder
     * @param array<string, mixed>                               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $instrumentChoices = [];

        foreach ($this->instrumentRepository->findAllOrderedByName() as $instrument) {
            $instrumentChoices[$instrument->getName()] = $instrument->getUuid()->toRfc4122();
        }

        $builder
            ->add('title', TextType::class, [
                'label' => 'sessions.summary.form.title',
            ])
            ->add('sessionDate', DateType::class, [
                'label' => 'sessions.summary.form.session_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'sessions.summary.form.subtitle',
                'required' => false,
            ])
            ->add('instrumentUuids', ChoiceType::class, [
                'label' => 'sessions.summary.form.instruments',
                'required' => false,
                'multiple' => true,
                'choices' => $instrumentChoices,
                'autocomplete' => true,
            ])
            ->add('generalNotes', TextareaType::class, [
                'label' => 'sessions.summary.form.private_notes',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'sessions.summary.form.submit',
                'attr' => ['class' => 'internal-button'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SessionSummaryFormModel::class,
            'translation_domain' => 'sessions',
        ]);
    }
}
