<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\DepartmentEntity;
use App\Infrastructure\Doctrine\Entity\RegionEntity;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<DepartmentEntity> */
final class DepartmentManagementType extends AbstractType
{
    /** @param FormBuilderInterface<DepartmentEntity|null> $builder @param array<string, mixed> $options */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, ['label' => 'technical.geography.form.code'])
            ->add('name', TextType::class, ['label' => 'technical.geography.form.name'])
            ->add('region', EntityType::class, ['class' => RegionEntity::class, 'choice_label' => static fn (RegionEntity $region): string => (string) $region, 'label' => 'technical.geography.form.region']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DepartmentEntity::class, 'translation_domain' => 'technical']);
    }
}
