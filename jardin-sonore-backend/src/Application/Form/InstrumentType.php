<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\InstrumentFormModel;
use App\Domain\Repository\InstrumentTagRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<InstrumentFormModel>
 */
final class InstrumentType extends AbstractType
{
    public function __construct(private readonly InstrumentTagRepositoryInterface $instrumentTagRepository)
    {
    }

    /**
     * @param FormBuilderInterface<InstrumentFormModel|null> $builder
     * @param array<string, mixed>                           $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tagChoices = [];

        foreach ($this->instrumentTagRepository->findAllOrderedByLabel() as $instrumentTag) {
            $tagChoices[$instrumentTag->getLabel()] = $instrumentTag->getUuid()->toRfc4122();
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'catalog.instrument.form.name',
            ])
            ->add('tuning', TextType::class, [
                'label' => 'catalog.instrument.form.tuning',
                'required' => false,
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'catalog.instrument.form.quantity',
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'catalog.instrument.form.notes',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('tagUuids', ChoiceType::class, [
                'label' => 'catalog.instrument.form.tags',
                'required' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $tagChoices,
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'catalog.instrument.form.active',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'catalog.instrument.form.submit',
                'attr' => ['class' => 'internal-button'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstrumentFormModel::class,
            'translation_domain' => 'catalog',
        ]);
    }
}
