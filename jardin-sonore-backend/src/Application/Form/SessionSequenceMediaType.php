<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\SessionSequenceMediaFormModel;
use App\Domain\Model\Session\MediaResourceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SessionSequenceMediaFormModel>
 */
final class SessionSequenceMediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, ['label' => 'sessions.sequence.form.media_label'])
            ->add('type', ChoiceType::class, [
                'label' => 'sessions.sequence.form.media_type',
                'choices' => MediaResourceType::cases(),
                'choice_label' => static fn (MediaResourceType $mediaResourceType): string => $mediaResourceType->translationKey(),
                'choice_translation_domain' => 'sessions',
            ])
            ->add('url', UrlType::class, ['label' => 'sessions.sequence.form.media_url']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SessionSequenceMediaFormModel::class]);
    }
}
