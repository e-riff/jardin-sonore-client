<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Form;

use App\Infrastructure\Admin\AddressContactCrudController;
use App\Infrastructure\Admin\MunicipalityCrudController;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CrudAutocompleteType;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<MunicipalityEntity>
 */
final class MunicipalityAutocompleteType extends AbstractType
{
    public function __construct(private readonly AdminUrlGeneratorInterface $adminUrlGenerator)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => MunicipalityEntity::class,
            'choice_label' => static function (MunicipalityEntity $municipality): string {
                $inseeCode = $municipality->getInseeCode();
                $label = $municipality->getName();

                return null !== $inseeCode && '' !== $inseeCode ? "{$inseeCode} — {$label}" : $label;
            },
            'attr' => [
                'data-ea-widget' => 'ea-autocomplete',
                'data-ea-autocomplete-endpoint-url' => $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(MunicipalityCrudController::class)
                    ->setAction('autocomplete')
                    ->set(AssociationField::PARAM_AUTOCOMPLETE_CONTEXT, [
                        EA::CRUD_CONTROLLER_FQCN => AddressContactCrudController::class,
                        'originatingPage' => Crud::PAGE_EDIT,
                        'propertyName' => 'municipality',
                    ])
                    ->generateUrl(),
                'data-placeholder' => '',
                'data-allow-clear' => 'true',
            ],
        ]);
    }

    public function getParent(): string
    {
        return CrudAutocompleteType::class;
    }
}
