<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Directory\OrganizationChoiceLabel;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/** @extends AbstractType<OrganizationEntity> */
#[AsEntityAutocompleteField]
final class OrganizationAutocompleteType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => OrganizationEntity::class,
            'multiple' => false,
            'required' => false,
            'placeholder' => 'sessions.summary.form.organization_placeholder',
            'min_characters' => 2,
            'max_results' => 20,
            'preload' => false,
            'choice_label' => OrganizationChoiceLabel::forEntity(...),
            'filter_query' => static function (QueryBuilder $queryBuilder, string $query): void {
                $queryBuilder
                    ->leftJoin('entity.contactDetails', 'contactDetails')
                    ->leftJoin('contactDetails.addressContacts', 'addressContact', 'WITH', 'addressContact.active = true')
                    ->leftJoin('addressContact.municipality', 'municipality')
                    ->andWhere('LOWER(entity.name) LIKE LOWER(:query) OR LOWER(municipality.name) LIKE LOWER(:query) OR LOWER(municipality.postalCode) LIKE LOWER(:query)')
                    ->setParameter('query', '%' . addcslashes(trim($query), '%_\\') . '%')
                    ->groupBy('entity.id')
                    ->orderBy('entity.name', 'ASC');
            },
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
