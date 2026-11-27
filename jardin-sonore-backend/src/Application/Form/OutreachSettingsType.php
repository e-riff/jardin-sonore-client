<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\OutreachSettingsFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<OutreachSettingsFormModel> */
final class OutreachSettingsType extends AbstractType
{
    /** @param FormBuilderInterface<OutreachSettingsFormModel|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('weeks', IntegerType::class, [
            'label' => 'mailing.outreach.window_label',
            'help' => 'mailing.outreach.window_help',
            'attr' => ['min' => 0, 'max' => 520],
        ])->add('save', SubmitType::class, [
            'label' => 'mailing.outreach.save_settings',
            'attr' => ['class' => 'internal-button internal-button--secondary'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OutreachSettingsFormModel::class, 'translation_domain' => 'mailing']);
    }
}
