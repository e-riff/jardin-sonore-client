<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/** @extends AbstractType<PersonEntity> */
#[AsEntityAutocompleteField]
final class CommercialPersonAutocompleteType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => PersonEntity::class,
            'required' => false,
            'min_characters' => 2,
            'max_results' => 20,
            'preload' => false,
            'choice_label' => static fn (PersonEntity $personEntity): string => (string) $personEntity . ' — ' . ($personEntity->getOrganization()?->getName() ?? ''),
            'filter_query' => static function (QueryBuilder $queryBuilder, string $query): void {
                $queryBuilder
                    ->leftJoin('entity.organization', 'organization')
                    ->andWhere('entity.active = true')
                    ->andWhere('LOWER(entity.firstName) LIKE LOWER(:query) OR LOWER(entity.lastName) LIKE LOWER(:query) OR LOWER(organization.name) LIKE LOWER(:query)')
                    ->setParameter('query', '%' . trim($query) . '%')
                    ->orderBy('entity.lastName', 'ASC');
            },
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
