<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\SessionSequenceFormModel;
use App\Domain\Repository\InstrumentRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SessionSequenceFormModel>
 */
final class RepertoireSessionSequenceType extends AbstractType
{
    public function __construct(private readonly InstrumentRepositoryInterface $instrumentRepository)
    {
    }

    /**
     * @param FormBuilderInterface<SessionSequenceFormModel|null> $builder
     * @param array<string, mixed>                                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $instrumentChoices = [];

        foreach ($this->instrumentRepository->findAllOrderedByName() as $instrument) {
            $instrumentChoices[$instrument->getName()] = $instrument->getUuid()->toRfc4122();
        }

        $builder
            ->add('role', TextType::class, [
                'label' => 'sessions.sequence.form.role',
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'sessions.sequence.form.notes',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('instrumentUuids', ChoiceType::class, [
                'label' => 'sessions.sequence.form.instruments',
                'required' => false,
                'multiple' => true,
                'choices' => $instrumentChoices,
                'autocomplete' => true,
            ])
            ->add('sourceUuid', HiddenType::class)
            ->add('sourceTitle', HiddenType::class)
            ->add('submit', SubmitType::class, [
                'label' => 'sessions.sequence.composer.add_repertoire_item',
                'attr' => ['class' => 'internal-button'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SessionSequenceFormModel::class,
            'translation_domain' => 'sessions',
        ]);
    }
}
