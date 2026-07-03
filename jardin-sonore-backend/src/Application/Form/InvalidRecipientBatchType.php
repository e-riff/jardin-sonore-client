<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\InvalidRecipientBatchFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<InvalidRecipientBatchFormModel>
 */
final class InvalidRecipientBatchType extends AbstractType
{
    /**
     * @param FormBuilderInterface<InvalidRecipientBatchFormModel|null> $builder
     * @param array<string, mixed>                                      $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('emails', TextareaType::class, [
                'label' => 'mailing.invalid_recipient.form.emails',
                'help' => 'mailing.invalid_recipient.form.emails_help',
                'attr' => [
                    'rows' => 10,
                    'placeholder' => 'mailing.invalid_recipient.form.emails_placeholder',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'mailing.invalid_recipient.form.submit',
                'attr' => [
                    'class' => 'internal-button',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvalidRecipientBatchFormModel::class,
            'translation_domain' => 'mailing',
        ]);
    }
}
