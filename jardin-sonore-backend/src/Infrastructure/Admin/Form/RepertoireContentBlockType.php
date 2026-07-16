<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Form;

use App\Domain\Model\Session\RepertoireBlockKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class RepertoireContentBlockType extends AbstractType
{
    /**
     * @param FormBuilderInterface<array<string, mixed>|null> $builder
     * @param array<string, mixed>                            $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('kind', ChoiceType::class, [
                'label' => 'admin.field.type',
                'choices' => [
                    'Ligne' => RepertoireBlockKind::LINE->value,
                    'Séparation' => RepertoireBlockKind::BREAK->value,
                ],
            ])
            ->add('text', TextareaType::class, [
                'label' => 'admin.field.text',
                'required' => false,
                'attr' => ['rows' => 1],
            ])
            ->add('gesture', TextareaType::class, [
                'label' => 'admin.field.gesture',
                'required' => false,
                'attr' => ['rows' => 1],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'backoffice',
        ]);
    }
}
