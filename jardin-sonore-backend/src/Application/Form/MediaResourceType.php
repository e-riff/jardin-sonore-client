<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\MediaResourceFormModel;
use App\Domain\Model\Session\MediaResourceType as ResourceType;
use App\Domain\Repository\ThemeRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Dropzone\Form\DropzoneType;

/**
 * @extends AbstractType<MediaResourceFormModel>
 */
final class MediaResourceType extends AbstractType
{
    public function __construct(private readonly ThemeRepositoryInterface $themeRepository)
    {
    }

    /**
     * @param FormBuilderInterface<MediaResourceFormModel|null> $builder
     * @param array<string, mixed>                              $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $themeChoices = [];
        foreach ($this->themeRepository->findAllOrderedByLabel() as $theme) {
            $themeChoices[$theme->getLabel()] = $theme->getUuid()->toRfc4122();
        }
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'sessions.media.form.type',
                'choices' => [
                    'sessions.media.type.soundtrack' => ResourceType::SOUNDTRACK,
                    'sessions.media.type.video' => ResourceType::VIDEO,
                    'sessions.media.type.link' => ResourceType::LINK,
                ],
                'choice_translation_domain' => 'sessions',
            ])
            ->add('title', TextType::class, ['label' => 'sessions.media.form.title'])
            ->add('primaryUrl', UrlType::class, [
                'label' => 'sessions.media.form.primary_url',
                'required' => false,
                'help' => 'sessions.media.form.primary_url_help',
                'attr' => [
                    'data-controller' => 'exclusive-resource-fields',
                    'data-exclusive-resource-fields-peer-selector-value' => '[name$="[primaryFile]"]',
                ],
            ])
            ->add('primaryFile', DropzoneType::class, [
                'label' => 'sessions.media.form.primary_file',
                'required' => false,
                'help' => 'sessions.media.form.primary_file_help',
                'attr' => [
                    'placeholder' => 'sessions.media.form.primary_file_placeholder',
                    'data-controller' => 'exclusive-resource-fields',
                    'data-exclusive-resource-fields-peer-selector-value' => '[name$="[primaryUrl]"]',
                ],
            ])
            ->add('source', TextType::class, ['label' => 'sessions.media.form.source', 'required' => false])
            ->add('description', TextareaType::class, ['label' => 'sessions.media.form.description', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('secondaryUrl', UrlType::class, ['label' => 'sessions.media.form.secondary_url', 'required' => false])
            ->add('imageUrl', UrlType::class, [
                'label' => 'sessions.media.form.image_url',
                'required' => false,
                'help' => 'sessions.media.form.image_url_help',
                'attr' => [
                    'data-controller' => 'exclusive-resource-fields',
                    'data-exclusive-resource-fields-peer-selector-value' => '[name$="[imageFile]"]',
                ],
            ])
            ->add('imageFile', DropzoneType::class, [
                'label' => 'sessions.media.form.image_file',
                'required' => false,
                'help' => 'sessions.media.form.image_file_help',
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                    'placeholder' => 'sessions.media.form.image_file_placeholder',
                    'data-controller' => 'exclusive-resource-fields',
                    'data-exclusive-resource-fields-peer-selector-value' => '[name$="[imageUrl]"]',
                ],
            ])
            ->add('themeUuids', ChoiceType::class, ['label' => 'sessions.media.form.themes', 'required' => false, 'multiple' => true, 'choices' => $themeChoices, 'autocomplete' => true])
            ->add('active', CheckboxType::class, ['label' => 'sessions.media.form.active', 'required' => false])
            ->add('submit', SubmitType::class, ['label' => 'sessions.media.form.submit', 'attr' => ['class' => 'internal-button']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MediaResourceFormModel::class,
            'translation_domain' => 'sessions',
        ]);
    }
}
