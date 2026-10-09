<?php

declare(strict_types=1);

namespace App\Application\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<array<string, mixed>> */
final class CommercialInvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reference', TextType::class, ['label' => 'invoice.reference'])
            ->add('amount', TextType::class, ['label' => 'invoice.amount'])
            ->add('issuedOn', DateType::class, ['label' => 'invoice.issued_on', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('submit', SubmitType::class, ['label' => 'invoice.save', 'attr' => ['class' => 'internal-button']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'commercial']);
    }
}
