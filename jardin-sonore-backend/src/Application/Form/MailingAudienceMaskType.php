<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\MailingAudienceMaskFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<MailingAudienceMaskFormModel>
 */
final class MailingAudienceMaskType extends AbstractType
{
    /**
     * @param FormBuilderInterface<MailingAudienceMaskFormModel|null> $builder
     * @param array<string, mixed>                                    $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'mailing.audience.mask.form.name',
                'help' => 'mailing.audience.mask.form.name_help',
                'required' => true,
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('currentAudienceSnapshot', HiddenType::class, [
                'required' => false,
                'attr' => [
                    'data-mailing-audience-target' => 'maskSnapshot',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'mailing.audience.mask.form.submit',
                'attr' => ['class' => 'internal-button'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MailingAudienceMaskFormModel::class,
            'translation_domain' => 'mailing',
        ]);
    }
}
