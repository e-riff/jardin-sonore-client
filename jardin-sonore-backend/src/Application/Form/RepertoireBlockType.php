<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\RepertoireBlockFormModel;
use App\Domain\Model\Session\RepertoireBlockKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RepertoireBlockFormModel>
 */
final class RepertoireBlockType extends AbstractType
{
    /**
     * @param FormBuilderInterface<RepertoireBlockFormModel|null> $builder
     * @param array<string, mixed>                                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('kind', ChoiceType::class, [
                'label' => 'sessions.repertoire.form.block.kind',
                'choices' => [
                    'sessions.repertoire.form.block.kind_line' => RepertoireBlockKind::LINE,
                    'sessions.repertoire.form.block.kind_break' => RepertoireBlockKind::BREAK,
                ],
                'choice_translation_domain' => 'sessions',
                'choice_value' => static fn (?RepertoireBlockKind $choice): string => null === $choice ? '' : $choice->value,
            ])
            ->add('text', TextareaType::class, [
                'label' => 'sessions.repertoire.form.block.text',
                'required' => false,
                'attr' => ['rows' => 1],
            ])
            ->add('gesture', TextareaType::class, [
                'label' => 'sessions.repertoire.form.block.gesture',
                'required' => false,
                'attr' => ['rows' => 1],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RepertoireBlockFormModel::class,
            'translation_domain' => 'sessions',
        ]);
    }
}
