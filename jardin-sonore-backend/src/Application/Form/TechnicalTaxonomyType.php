<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\TechnicalTaxonomyFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<TechnicalTaxonomyFormModel> */
final class TechnicalTaxonomyType extends AbstractType
{
    /**
     * @param FormBuilderInterface<TechnicalTaxonomyFormModel|null> $builder
     * @param array<string, mixed>                                  $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, ['label' => 'technical.taxonomy.form.label', 'attr' => ['maxlength' => 255]])
            ->add('color', TextType::class, [
                'label' => 'technical.taxonomy.form.color',
                'attr' => [
                    'maxlength' => 32,
                    'autocomplete' => 'off',
                    'data-color-input-target' => 'hex',
                    'data-action' => 'input->color-input#syncPicker',
                ],
            ])
            ->add('colorPicker', ColorType::class, [
                'label' => false,
                'mapped' => false,
                'data' => $builder->getData() instanceof TechnicalTaxonomyFormModel ? $builder->getData()->color : '#64748b',
                'attr' => [
                    'data-color-input-target' => 'picker',
                    'data-action' => 'input->color-input#syncHex',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TechnicalTaxonomyFormModel::class]);
    }
}
