<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<UserOrganizationAccessEntity> */
final class UserOrganizationAccessType extends AbstractType
{
    /**
     * @param FormBuilderInterface<UserOrganizationAccessEntity|null> $builder
     * @param array<string, mixed>                                    $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('organization', OrganizationAutocompleteType::class, [
            'label' => 'technical.portal_user.form.organization',
            'placeholder' => 'technical.portal_user.form.organization_placeholder',
            'multiple' => false,
            'required' => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserOrganizationAccessEntity::class,
            'translation_domain' => 'technical',
        ]);
    }
}
