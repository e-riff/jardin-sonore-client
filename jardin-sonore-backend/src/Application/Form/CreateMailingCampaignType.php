<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\CreateMailingCampaignFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Dropzone\Form\DropzoneType;

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
                'help_html' => true,
                'attr' => [
                    'rows' => 8,
                ],
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'mailing.form.subtitle',
                'help' => 'mailing.form.subtitle_help',
                'required' => false,
            ])
            ->add('callToActionLabel', TextType::class, [
                'label' => 'mailing.form.call_to_action_label',
                'help' => 'mailing.form.call_to_action_label_help',
                'required' => false,
            ])
            ->add('callToActionUrl', UrlType::class, [
                'label' => 'mailing.form.call_to_action_url',
                'help' => 'mailing.form.call_to_action_url_help',
                'required' => false,
            ])
            ->add('bannerImageFile', DropzoneType::class, [
                'label' => 'mailing.form.banner_image',
                'help' => 'mailing.form.banner_image_help',
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                    'placeholder' => 'mailing.form.banner_image_placeholder',
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
