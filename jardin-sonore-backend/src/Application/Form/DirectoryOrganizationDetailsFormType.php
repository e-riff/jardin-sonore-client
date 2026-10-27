<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** @extends AbstractType<OrganizationEntity> */
final class DirectoryOrganizationDetailsFormType extends AbstractType
{
    /** @param FormBuilderInterface<OrganizationEntity|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'admin.field.name', 'constraints' => [new NotBlank(), new Length(max: 255)]])
            ->add('type', ChoiceType::class, ['label' => 'admin.field.organization_type', 'choices' => $this->typeChoices(), 'choice_translation_domain' => 'backoffice', 'placeholder' => '', 'required' => false])
            ->add('sector', ChoiceType::class, ['label' => 'admin.field.organization_sector', 'choices' => $this->sectorChoices(), 'choice_translation_domain' => 'backoffice', 'placeholder' => '', 'required' => false])
            ->add('websiteUrl', UrlType::class, ['label' => 'admin.field.website_url', 'required' => false])
            ->add('active', CheckboxType::class, ['label' => 'admin.field.active', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OrganizationEntity::class, 'translation_domain' => 'backoffice']);
    }

    /** @return array<string, OrganizationType> */
    private function typeChoices(): array
    {
        $choices = [];
        foreach (OrganizationType::cases() as $type) {
            $choices['address_book.organization_type.' . $type->value] = $type;
        }

        return $choices;
    }

    /** @return array<string, OrganizationSector> */
    private function sectorChoices(): array
    {
        $choices = [];
        foreach (OrganizationSector::cases() as $sector) {
            $choices['address_book.organization_sector.' . $sector->value] = $sector;
        }

        return $choices;
    }
}
