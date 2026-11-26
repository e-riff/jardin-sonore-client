<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\MailingIntroBlockFormModel;
use App\Application\Form\Model\MailingSectionFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<MailingSectionFormModel> */
final class MailingSectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('enabled', CheckboxType::class, ['required' => false, 'label' => 'mailing.form.section_enabled'])
            ->add('blocks', CollectionType::class, [
                'entry_type' => MailingIntroBlockType::class, 'entry_options' => ['locked' => $options['disabled']],
                'allow_add' => !$options['disabled'], 'allow_delete' => !$options['disabled'],
                'by_reference' => false, 'label' => false,
                'delete_empty' => static fn (mixed $block): bool => $block instanceof MailingIntroBlockFormModel && 'paragraph' === $block->kind && '' === trim(strip_tags($block->text)),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MailingSectionFormModel::class, 'translation_domain' => 'mailing']);
    }
}
