<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\CommercialQuoteEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class CommercialQuoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reference', TextType::class, ['label' => 'quote.reference'])
            ->add('filename', TextType::class, ['label' => 'quote.filename'])
            ->add('amount', TextType::class, ['label' => 'quote.amount'])
            ->add('sentOn', DateType::class, ['label' => 'quote.sent_on', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('replaces', ChoiceType::class, [
                'label' => 'quote.replaces',
                'required' => false,
                'placeholder' => 'quote.no_replacement',
                'choices' => $options['quotes'],
                'choice_label' => static fn (CommercialQuoteEntity $quoteEntity): string => $quoteEntity->getReference(),
                'choice_value' => static fn (?CommercialQuoteEntity $quoteEntity): string => (string) ($quoteEntity?->getId() ?? ''),
            ])
            ->add('followup', CheckboxType::class, ['label' => 'quote.followup', 'required' => false])
            ->add('submit', SubmitType::class, ['label' => 'quote.save', 'attr' => ['class' => 'internal-button']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'commercial', 'quotes' => []]);
        $resolver->setAllowedTypes('quotes', 'array');
    }
}
