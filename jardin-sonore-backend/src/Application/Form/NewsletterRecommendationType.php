<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\NewsletterRecommendationFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Dropzone\Form\DropzoneType;

/**
 * @extends AbstractType<NewsletterRecommendationFormModel>
 */
final class NewsletterRecommendationType extends AbstractType
{
    /**
     * @param FormBuilderInterface<NewsletterRecommendationFormModel|null> $builder
     * @param array<string, mixed>                                         $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'mailing.catalog.form.title',
                'help' => 'mailing.catalog.form.title_help',
            ])
            ->add('tag', TextType::class, [
                'label' => 'mailing.catalog.form.tag',
                'help' => 'mailing.catalog.form.tag_help',
                'required' => false,
            ])
            ->add('text', TextareaType::class, [
                'label' => 'mailing.catalog.form.text',
                'help' => 'mailing.catalog.form.text_help',
                'attr' => ['rows' => 5],
            ])
            ->add('url', UrlType::class, [
                'label' => 'mailing.catalog.form.url',
                'help' => 'mailing.catalog.form.url_help',
                'required' => false,
            ])
            ->add('linkLabel', TextType::class, [
                'label' => 'mailing.catalog.form.link_label',
                'help' => 'mailing.catalog.form.link_label_help',
                'required' => false,
            ])
            ->add('imageFile', DropzoneType::class, [
                'label' => 'mailing.catalog.form.image',
                'help' => 'mailing.catalog.form.image_help',
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                    'placeholder' => 'mailing.catalog.form.image_placeholder',
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'mailing.catalog.form.active',
                'help' => 'mailing.catalog.form.active_help',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'mailing.catalog.form.submit',
                'attr' => ['class' => 'internal-button'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NewsletterRecommendationFormModel::class,
            'translation_domain' => 'mailing',
        ]);
    }
}
