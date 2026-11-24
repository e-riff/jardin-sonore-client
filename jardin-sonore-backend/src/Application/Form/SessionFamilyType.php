<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\Model\SessionFamilyFormModel;
use App\Domain\Repository\ThemeRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<SessionFamilyFormModel> */
final class SessionFamilyType extends AbstractType
{
    public function __construct(private readonly ThemeRepositoryInterface $themeRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $themes = [];
        foreach ($this->themeRepository->findAllOrderedByLabel() as $theme) {
            $themes[$theme->getLabel()] = $theme->getUuid()->toRfc4122();
        }
        $builder
            ->add('name', TextType::class, ['label' => 'sessions.family.name'])
            ->add('themeUuids', ChoiceType::class, ['label' => 'sessions.family.themes', 'choices' => $themes, 'multiple' => true, 'required' => false, 'autocomplete' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SessionFamilyFormModel::class, 'translation_domain' => 'sessions']);
    }
}
