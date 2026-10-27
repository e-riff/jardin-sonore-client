<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/** @extends AbstractType<MunicipalityEntity> */
#[AsEntityAutocompleteField]
final class DirectoryMunicipalityAutocompleteType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => MunicipalityEntity::class,
            'required' => false,
            'placeholder' => 'technical.directory.form.municipality_placeholder',
            'translation_domain' => 'technical',
            'min_characters' => 2,
            'max_results' => 20,
            'preload' => false,
            'choice_label' => static function (MunicipalityEntity $municipality): string {
                $department = $municipality->getDepartment();
                $code = $municipality->getInseeCode() ?? $municipality->getPostalCode() ?? '';

                return trim($code . ' — ' . $municipality->getName() . (null !== $department ? ' (' . $department->getCode() . ')' : ''));
            },
            'filter_query' => static function (QueryBuilder $queryBuilder, string $query): void {
                $queryBuilder->leftJoin('entity.department', 'department')
                    ->andWhere('LOWER(entity.name) LIKE LOWER(:query) OR LOWER(entity.inseeCode) LIKE LOWER(:query) OR LOWER(entity.postalCode) LIKE LOWER(:query) OR LOWER(department.code) LIKE LOWER(:query)')
                    ->setParameter('query', '%' . addcslashes(trim($query), '%_\\') . '%')
                    ->orderBy('entity.name', 'ASC');
            },
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
