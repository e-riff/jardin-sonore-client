<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** @extends AbstractType<PersonEntity> */
final class DirectoryPersonDetailsFormType extends AbstractType
{
    /** @param FormBuilderInterface<PersonEntity|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lastName', TextType::class, ['label' => 'admin.field.last_name', 'constraints' => [new NotBlank(), new Length(max: 255)]])
            ->add('firstName', TextType::class, ['label' => 'admin.field.first_name', 'constraints' => [new NotBlank(), new Length(max: 255)]])
            ->add('role', TextType::class, ['label' => 'admin.field.role', 'required' => false, 'constraints' => [new Length(max: 255)]])
            ->add('organization', OrganizationAutocompleteType::class, ['label' => 'technical.directory.form.organization', 'placeholder' => 'technical.directory.form.organization_placeholder', 'translation_domain' => 'technical'])
            ->add('active', CheckboxType::class, ['label' => 'admin.field.active', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PersonEntity::class, 'translation_domain' => 'backoffice']);
    }
}
