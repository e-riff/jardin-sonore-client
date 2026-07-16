<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\SessionRecommendationFormModel;
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
 * @extends AbstractType<SessionRecommendationFormModel>
 */
final class SessionRecommendationType extends AbstractType
{
    /**
     * @param FormBuilderInterface<SessionRecommendationFormModel|null> $builder
     * @param array<string, mixed>                                      $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'sessions.recommendation.form.title'])
            ->add('text', TextareaType::class, ['label' => 'sessions.recommendation.form.text', 'attr' => ['rows' => 5]])
            ->add('notes', TextareaType::class, ['label' => 'sessions.recommendation.form.notes', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('primaryUrl', UrlType::class, ['label' => 'sessions.recommendation.form.primary_url', 'required' => false])
            ->add('secondaryUrl', UrlType::class, ['label' => 'sessions.recommendation.form.secondary_url', 'required' => false])
            ->add('imageUrl', UrlType::class, [
                'label' => 'sessions.recommendation.form.image_url',
                'required' => false,
                'help' => 'sessions.recommendation.form.image_url_help',
            ])
            ->add('imageFile', DropzoneType::class, [
                'label' => 'sessions.recommendation.form.image_file',
                'required' => false,
                'help' => 'sessions.recommendation.form.image_file_help',
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                    'placeholder' => 'sessions.recommendation.form.image_file_placeholder',
                ],
            ])
            ->add('active', CheckboxType::class, ['label' => 'sessions.recommendation.form.active', 'required' => false])
            ->add('submit', SubmitType::class, ['label' => 'sessions.recommendation.form.submit', 'attr' => ['class' => 'internal-button']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SessionRecommendationFormModel::class,
            'translation_domain' => 'sessions',
        ]);
    }
}
