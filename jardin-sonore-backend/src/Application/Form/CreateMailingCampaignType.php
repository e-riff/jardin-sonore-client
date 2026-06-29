<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\CreateMailingCampaignFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CreateMailingCampaignFormModel>
 */
final class CreateMailingCampaignType extends AbstractType
{
    /**
     * @param FormBuilderInterface<CreateMailingCampaignFormModel|null> $builder
     * @param array<string, mixed>                                      $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('internalTitle', TextType::class, [
                'label' => 'mailing.form.internal_title',
                'help' => 'mailing.form.internal_title_help',
            ])
            ->add('emailSubject', TextType::class, [
                'label' => 'mailing.form.email_subject',
                'help' => 'mailing.form.email_subject_help',
            ])
            ->add('publicTitle', TextType::class, [
                'label' => 'mailing.form.public_title',
                'help' => 'mailing.form.public_title_help',
            ])
            ->add('mainText', TextareaType::class, [
                'label' => 'mailing.form.main_text',
                'help' => 'mailing.form.main_text_help',
                'attr' => [
                    'rows' => 8,
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'mailing.form.submit',
                'attr' => [
                    'class' => 'internal-button',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateMailingCampaignFormModel::class,
            'translation_domain' => 'mailing',
        ]);
    }
}
