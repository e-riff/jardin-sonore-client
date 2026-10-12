<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\MailingIntroBlockFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Dropzone\Form\DropzoneType;

/** @extends AbstractType<MailingIntroBlockFormModel> */
final class MailingIntroBlockType extends AbstractType
{
    /**
     * @param FormBuilderInterface<MailingIntroBlockFormModel|null> $builder
     * @param array<string, mixed>                                  $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $disabled = true === $options['locked'];

        $builder
            ->add('id', HiddenType::class)
            ->add('kind', ChoiceType::class, [
                'label' => 'mailing.form.intro_kind',
                'choices' => [
                    'mailing.form.intro_paragraph' => 'paragraph',
                    'mailing.form.intro_image' => 'image',
                    'mailing.form.intro_split' => 'split',
                ],
                'disabled' => $disabled,
            ])
            ->add('text', TextareaType::class, [
                'label' => 'mailing.form.intro_text',
                'required' => false,
                'disabled' => $disabled,
                'empty_data' => '',
                'attr' => ['rows' => 5],
            ])
            ->add('imageFile', DropzoneType::class, [
                'label' => 'mailing.form.intro_image_file',
                'required' => false,
                'disabled' => $disabled,
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            ])
            ->add('imageAlt', TextType::class, [
                'label' => 'mailing.form.intro_image_alt',
                'required' => false,
                'disabled' => $disabled,
            ])
            ->add('imageDecorative', CheckboxType::class, [
                'label' => 'mailing.form.intro_image_decorative',
                'required' => false,
                'disabled' => $disabled,
            ])
            ->add('imageSide', ChoiceType::class, [
                'label' => 'mailing.form.intro_image_side',
                'choices' => [
                    'mailing.form.intro_image_left' => 'left',
                    'mailing.form.intro_image_right' => 'right',
                ],
                'disabled' => $disabled,
                'required' => false,
                'empty_data' => 'right',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MailingIntroBlockFormModel::class,
            'translation_domain' => 'mailing',
            'locked' => false,
        ]);
    }
}
