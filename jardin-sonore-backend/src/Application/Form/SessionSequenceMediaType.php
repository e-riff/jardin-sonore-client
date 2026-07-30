<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\SessionSequenceMediaFormModel;
use App\Domain\Model\Session\MediaResourceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
        $builder->add('label', TextType::class)->add('type', ChoiceType::class, ['choices' => MediaResourceType::cases()])->add('url', UrlType::class)->add('imageUrl', UrlType::class, ['required' => false])->add('featured', CheckboxType::class, ['required' => false])->add('displayOnSession', CheckboxType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SessionSequenceMediaFormModel::class]);
    }
}
