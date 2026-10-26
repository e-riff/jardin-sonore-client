<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\DepartmentEntity;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<MunicipalityEntity> */
final class MunicipalityManagementType extends AbstractType
{
    /**
     * @param FormBuilderInterface<MunicipalityEntity|null> $builder
     * @param array<string, mixed>                          $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'technical.geography.municipalities.name'])
            ->add('inseeCode', TextType::class, ['label' => 'technical.geography.municipalities.insee_code', 'required' => false])
            ->add('postalCode', TextType::class, ['label' => 'technical.geography.municipalities.postal_code', 'required' => false])
            ->add('department', EntityType::class, [
                'class' => DepartmentEntity::class,
                'choice_label' => static fn (DepartmentEntity $departmentEntity): string => (string) $departmentEntity,
                'label' => 'technical.geography.municipalities.department',
            ])
            ->add('address', TextareaType::class, [
                'label' => 'technical.geography.municipalities.address',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('phoneNumber', TelType::class, ['label' => 'technical.geography.municipalities.phone', 'required' => false])
            ->add('emailAddress', EmailType::class, ['label' => 'technical.geography.municipalities.email', 'required' => false])
            ->add('siren', TextType::class, ['label' => 'technical.geography.municipalities.siren', 'required' => false])
            ->add('siret', TextType::class, ['label' => 'technical.geography.municipalities.siret', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MunicipalityEntity::class,
            'translation_domain' => 'technical',
        ]);
    }
}
