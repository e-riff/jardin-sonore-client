<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\SessionSequenceFormModel;
use App\Domain\Model\Session\SessionSequenceType as SequenceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SessionSequenceFormModel>
 */
final class SessionSequenceType extends AbstractType
{
    /**
     * @param FormBuilderInterface<SessionSequenceFormModel|null> $builder
     * @param array<string, mixed>                                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'sessions.sequence.form.type',
                'choices' => [
                    'sessions.sequence.type.warmup' => SequenceType::WARMUP,
                    'sessions.sequence.type.nursery_rhyme' => SequenceType::NURSERY_RHYME,
                    'sessions.sequence.type.fingerplay' => SequenceType::FINGERPLAY,
                    'sessions.sequence.type.soundtrack' => SequenceType::SOUNDTRACK,
                    'sessions.sequence.type.manipulation' => SequenceType::MANIPULATION,
                    'sessions.sequence.type.movement' => SequenceType::MOVEMENT,
                    'sessions.sequence.type.closing' => SequenceType::CLOSING,
                    'sessions.sequence.type.free' => SequenceType::FREE,
                ],
                'choice_translation_domain' => 'sessions',
            ])
            ->add('title', TextType::class, [
                'label' => 'sessions.sequence.form.title',
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'sessions.sequence.form.subtitle',
                'required' => false,
            ])
            ->add('body', TextareaType::class, [
                'label' => 'sessions.sequence.form.body',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('lyrics', TextareaType::class, [
                'label' => 'sessions.sequence.form.lyrics',
                'required' => false,
                'attr' => ['rows' => 8],
            ])
            ->add('gestures', TextareaType::class, [
                'label' => 'sessions.sequence.form.gestures',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'sessions.sequence.form.notes',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('primaryUrl', UrlType::class, [
                'label' => 'sessions.sequence.form.primary_url',
                'required' => false,
            ])
            ->add('secondaryUrl', UrlType::class, [
                'label' => 'sessions.sequence.form.secondary_url',
                'required' => false,
            ])
            ->add('imageUrl', UrlType::class, [
                'label' => 'sessions.sequence.form.image_url',
                'required' => false,
            ])
            ->add('showLyricsByDefault', CheckboxType::class, [
                'label' => 'sessions.sequence.form.show_lyrics_by_default',
                'required' => false,
            ])
            ->add('sourceUuid', HiddenType::class, [
                'required' => false,
            ])
            ->add('sourceTitle', HiddenType::class, [
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'sessions.sequence.form.submit',
                'attr' => ['class' => 'internal-button'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SessionSequenceFormModel::class,
            'translation_domain' => 'sessions',
        ]);
    }
}
