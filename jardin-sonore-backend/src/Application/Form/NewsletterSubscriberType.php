<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\NewsletterSubscriberFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<NewsletterSubscriberFormModel> */
final class NewsletterSubscriberType extends AbstractType
{
    /** @param FormBuilderInterface<NewsletterSubscriberFormModel|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('emailAddress', EmailType::class, ['label' => 'subscriber.email', 'disabled' => $options['locked_email'], 'attr' => ['maxlength' => 255]])
            ->add('consentAttested', CheckboxType::class, ['label' => 'subscriber.consent', 'help' => 'subscriber.consent_help', 'required' => true])
            ->add('submit', SubmitType::class, ['label' => 'subscriber.activate', 'attr' => ['class' => 'internal-button']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => NewsletterSubscriberFormModel::class, 'translation_domain' => 'newsletter', 'locked_email' => false]);
        $resolver->setAllowedTypes('locked_email', 'bool');
    }
}
