<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\SessionSequenceFormModel;
use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<SessionSequenceFormModel>
 */
final class SessionSequenceType extends AbstractType
{
    public function __construct(
        private readonly InstrumentRepositoryInterface $instrumentRepository,
        private readonly MediaResourceRepositoryInterface $mediaResourceRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param FormBuilderInterface<SessionSequenceFormModel|null> $builder
     * @param array<string, mixed>                                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $instrumentChoices = [];

        foreach ($this->instrumentRepository->findAllOrderedByName() as $instrument) {
            $instrumentChoices[$instrument->getName()] = $instrument->getUuid()->toRfc4122();
        }
        $mediaResources = $this->mediaResourceRepository->search();
        $mediaLabelsByUuid = MediaResourceChoiceLabels::byUuid($mediaResources, $this->translator);
        $mediaChoices = [];
        foreach ($mediaResources as $mediaResource) {
            $mediaUuid = $mediaResource->getUuid()->toRfc4122();
            $mediaChoices[$mediaLabelsByUuid[$mediaUuid]] = $mediaUuid;
        }

        $builder
            ->add('title', TextType::class, [
                'label' => 'sessions.sequence.form.title',
            ])
            ->add('catalogMediaUuids', ChoiceType::class, [
                'label' => 'sessions.sequence.form.catalog_media',
                'required' => false,
                'multiple' => true,
                'choices' => $mediaChoices,
                'autocomplete' => true,
            ])
            ->add('role', TextType::class, [
                'label' => 'sessions.sequence.form.role',
                'required' => false,
                'attr' => ['list' => 'session-sequence-role-suggestions'],
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
            ])
            ->add('instrumentUuids', ChoiceType::class, [
                'label' => 'sessions.sequence.form.instruments',
                'required' => false,
                'multiple' => true,
                'choices' => $instrumentChoices,
                'autocomplete' => true,
            ]);

        $builder
            ->add('body', TextareaType::class, [
                'label' => 'sessions.sequence.form.body',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'sessions.sequence.form.subtitle',
                'required' => false,
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
            ->add('showLyricsByDefault', CheckboxType::class, [
                'label' => 'sessions.sequence.form.show_lyrics_by_default',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SessionSequenceFormModel::class,
            'translation_domain' => 'sessions',
            'activity_only' => false,
        ]);
        $resolver->setAllowedTypes('activity_only', 'bool');
    }
}
