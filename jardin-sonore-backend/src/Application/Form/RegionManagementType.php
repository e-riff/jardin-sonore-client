<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\RegionEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<RegionEntity> */
final class RegionManagementType extends AbstractType
{
    /** @param FormBuilderInterface<RegionEntity|null> $builder @param array<string, mixed> $options */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, ['label' => 'technical.geography.form.code'])
            ->add('name', TextType::class, ['label' => 'technical.geography.form.name']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RegionEntity::class, 'translation_domain' => 'technical']);
    }
}
