<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\RepertoireBlockFormModel;
use App\Application\Form\Model\RepertoireItemFormModel;
use App\Domain\Model\Session\RepertoireItemType as RepertoireItemKind;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RepertoireItemFormModel>
 */
final class RepertoireItemType extends AbstractType
{
    public function __construct(private readonly MediaResourceRepositoryInterface $mediaResourceRepository)
    {
    }

    /**
     * @param FormBuilderInterface<RepertoireItemFormModel|null> $builder
     * @param array<string, mixed>                               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mediaChoices = [];
        $mediaChoiceAttributes = [];

        foreach ($this->mediaResourceRepository->search(activeOnly: true) as $mediaResource) {
            $mediaUuid = $mediaResource->getUuid()->toRfc4122();
            $mediaChoices[$mediaResource->getTitle()] = $mediaUuid;
            $mediaChoiceAttributes[$mediaUuid] = [
                'data-media-title' => $mediaResource->getTitle(),
                'data-media-type' => $mediaResource->getType()->translationKey(),
                'data-media-url' => $mediaResource->getPrimaryUrl(),
            ];
        }

        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'sessions.repertoire.form.type',
                'choices' => [
                    'sessions.repertoire.type.nursery_rhyme' => RepertoireItemKind::NURSERY_RHYME,
                    'sessions.repertoire.type.fingerplay' => RepertoireItemKind::FINGERPLAY,
                ],
                'choice_translation_domain' => 'sessions',
            ])
            ->add('title', TextType::class, ['label' => 'sessions.repertoire.form.title'])
            ->add('source', TextType::class, ['label' => 'sessions.repertoire.form.source', 'required' => false])
            ->add('importText', TextareaType::class, [
                'label' => 'sessions.repertoire.form.import_text',
                'required' => false,
                'attr' => ['rows' => 8],
            ])
            ->add('contentBlocks', CollectionType::class, [
                'label' => 'sessions.repertoire.form.content_blocks',
                'entry_type' => RepertoireBlockType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
            ->add('notes', TextareaType::class, ['label' => 'sessions.repertoire.form.notes', 'required' => false, 'attr' => ['rows' => 5]])
            ->add('linkedMediaUuids', ChoiceType::class, [
                'label' => 'sessions.repertoire.form.linked_media',
                'required' => false,
                'multiple' => true,
                'choices' => $mediaChoices,
                'autocomplete' => true,
                'choice_attr' => static fn (?string $choice) => null === $choice ? [] : ($mediaChoiceAttributes[$choice] ?? []),
            ])
            ->add('active', CheckboxType::class, ['label' => 'sessions.repertoire.form.active', 'required' => false])
            ->add('submit', SubmitType::class, ['label' => 'sessions.repertoire.form.submit', 'attr' => ['class' => 'internal-button']]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $formEvent): void {
            $data = $formEvent->getData();

            if (!$data instanceof RepertoireItemFormModel || [] !== $data->contentBlocks) {
                return;
            }

            $data->contentBlocks = [RepertoireBlockFormModel::createLine()];
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RepertoireItemFormModel::class,
            'translation_domain' => 'sessions',
        ]);
    }
}
