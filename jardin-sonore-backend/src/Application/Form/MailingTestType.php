<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\MailingTestFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<MailingTestFormModel>
 */
final class MailingTestType extends AbstractType
{
    /**
     * @param FormBuilderInterface<MailingTestFormModel|null> $builder
     * @param array<string, mixed>                            $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipientEmail', EmailType::class, [
                'label' => 'mailing.test.form.recipient_email',
                'help' => 'mailing.test.form.recipient_email_help',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'mailing.test.form.submit',
                'attr' => [
                    'class' => 'internal-button',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MailingTestFormModel::class,
            'translation_domain' => 'mailing',
        ]);
    }
}
