<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\RepertoireYoutubeVideoFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RepertoireYoutubeVideoFormModel>
 */
final class RepertoireYoutubeVideoType extends AbstractType
{
    /**
     * @param FormBuilderInterface<RepertoireYoutubeVideoFormModel|null> $builder
     * @param array<string, mixed>                                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('youtubeUrl', UrlType::class, [
                'label' => 'sessions.sequence.composer.youtube_url',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'sessions.sequence.composer.youtube_submit',
                'attr' => ['class' => 'internal-button'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RepertoireYoutubeVideoFormModel::class,
            'translation_domain' => 'sessions',
        ]);
    }
}
