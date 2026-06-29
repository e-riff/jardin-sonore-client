<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\MailingRecommendationFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<MailingRecommendationFormModel>
 */
final class MailingRecommendationType extends AbstractType
{
    /**
     * @param FormBuilderInterface<MailingRecommendationFormModel|null> $builder
     * @param array<string, mixed>                                      $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('uuid', HiddenType::class)
            ->add('title', TextType::class, [
                'label' => 'mailing.form.recommendation.title',
                'help' => 'mailing.form.recommendation.title_help',
            ])
            ->add('text', TextareaType::class, [
                'label' => 'mailing.form.recommendation.text',
                'help' => 'mailing.form.recommendation.text_help',
                'attr' => [
                    'rows' => 4,
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'mailing.form.recommendation.url',
                'help' => 'mailing.form.recommendation.url_help',
                'required' => false,
            ])
            ->add('linkLabel', TextType::class, [
                'label' => 'mailing.form.recommendation.link_label',
                'help' => 'mailing.form.recommendation.link_label_help',
                'required' => false,
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'mailing.form.recommendation.active',
                'help' => 'mailing.form.recommendation.active_help',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'mailing.form.recommendation.save',
                'attr' => ['class' => 'internal-button'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MailingRecommendationFormModel::class,
            'translation_domain' => 'mailing',
        ]);
    }
}
