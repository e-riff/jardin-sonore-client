<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\EditMailingCampaignFormModel;
use App\Application\Form\Model\MailingIntroBlockFormModel;
use App\Domain\Model\Mailing\MailingCampaignType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Dropzone\Form\DropzoneType;

/**
 * @extends AbstractType<EditMailingCampaignFormModel>
 */
final class EditMailingCampaignType extends AbstractType
{
    /**
     * @param FormBuilderInterface<EditMailingCampaignFormModel|null> $builder
     * @param array<string, mixed>                                    $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $locked = true === $options['locked'];

        $builder
            ->add('campaignType', EnumType::class, [
                'class' => MailingCampaignType::class, 'label' => 'mailing.form.campaign_type',
                'choice_label' => static fn (MailingCampaignType $type): string => 'mailing.type.' . $type->value,
                'placeholder' => 'mailing.form.choose_type',
                'disabled' => $locked || ($builder->getData() instanceof EditMailingCampaignFormModel && $builder->getData()->usesStructuredContent),
            ])
            ->add('internalTitle', TextType::class, [
                'label' => 'mailing.form.internal_title',
                'help' => 'mailing.form.internal_title_help',
                'disabled' => $locked,
            ])
            ->add('emailSubject', TextType::class, [
                'label' => 'mailing.form.email_subject',
                'help' => 'mailing.form.email_subject_help',
                'disabled' => $locked,
            ])
            ->add('preheader', TextType::class, [
                'label' => 'mailing.form.preheader',
                'help' => 'mailing.form.preheader_help',
                'required' => false,
                'disabled' => $locked,
            ])
            ->add('publicTitle', TextType::class, [
                'label' => 'mailing.form.public_title',
                'help' => 'mailing.form.public_title_help',
                'disabled' => $locked,
            ])
            ->add('mainText', TextareaType::class, [
                'label' => 'mailing.form.main_text',
                'help' => 'mailing.form.main_text_help',
                'help_html' => true,
                'required' => false,
                'empty_data' => '',
                'disabled' => $locked,
                'attr' => [
                    'rows' => 10,
                ],
            ])
            ->add('introBlocks', CollectionType::class, [
                'entry_type' => MailingIntroBlockType::class,
                'entry_options' => ['locked' => $locked],
                'allow_add' => !$locked,
                'allow_delete' => !$locked,
                'delete_empty' => static fn (mixed $block): bool => $block instanceof MailingIntroBlockFormModel
                    && 'paragraph' === $block->kind
                    && '' === trim(strip_tags($block->text)),
                'by_reference' => false,
                'prototype' => !$locked,
                'disabled' => $locked,
                'label' => false,
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'mailing.form.subtitle',
                'help' => 'mailing.form.subtitle_help',
                'required' => false,
                'disabled' => $locked,
            ])
            ->add('callToActionLabel', TextType::class, [
                'label' => 'mailing.form.call_to_action_label',
                'help' => 'mailing.form.call_to_action_label_help',
                'required' => false,
                'disabled' => $locked,
            ])
            ->add('callToActionUrl', UrlType::class, [
                'label' => 'mailing.form.call_to_action_url',
                'help' => 'mailing.form.call_to_action_url_help',
                'required' => false,
                'disabled' => $locked,
            ])
            ->add('bannerImageFile', DropzoneType::class, [
                'label' => 'mailing.form.banner_image',
                'help' => 'mailing.form.banner_image_help',
                'required' => false,
                'disabled' => $locked,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                    'placeholder' => 'mailing.form.banner_image_placeholder',
                ],
            ])
            ->add('removeBannerImage', CheckboxType::class, [
                'label' => 'mailing.form.banner_image_remove',
                'help' => 'mailing.form.banner_image_remove_help',
                'required' => false,
                'disabled' => $locked,
            ])
            ->add('templateKey', ChoiceType::class, [
                'label' => 'mailing.form.template',
                'help' => 'mailing.form.template_help',
                'disabled' => $locked || ($builder->getData() instanceof EditMailingCampaignFormModel && $builder->getData()->usesStructuredContent),
                'choices' => $builder->getData() instanceof EditMailingCampaignFormModel && $builder->getData()->usesStructuredContent
                    ? ['mailing.type.' . $builder->getData()->campaignType->value => $builder->getData()->campaignType->value]
                    : ['mailing.form.template_default' => 'default'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'mailing.form.save',
                'disabled' => $locked,
                'attr' => [
                    'class' => 'internal-button',
                ],
            ]);
        $model = $builder->getData();
        if ($model instanceof EditMailingCampaignFormModel && $model->usesStructuredContent) {
            $builder->add('sections', CollectionType::class, [
                'entry_type' => MailingSectionType::class, 'entry_options' => ['disabled' => $locked],
                'allow_add' => false, 'allow_delete' => false, 'by_reference' => false, 'label' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EditMailingCampaignFormModel::class,
            'locked' => false,
            'translation_domain' => 'mailing',
        ]);
    }
}
