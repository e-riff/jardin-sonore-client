<?php

declare(strict_types=1);

namespace App\Application\Form;

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
            'required' => false,
            'placeholder' => 'sessions.summary.form.organization_placeholder',
            'min_characters' => 2,
            'max_results' => 20,
            'preload' => false,
            'choice_label' => static fn (OrganizationEntity $organizationEntity): string => $organizationEntity->getName() . ' — ' . $organizationEntity->getMunicipalitySummary() . ' — ' . $organizationEntity->getEmailContactsSummary(),
            'filter_query' => static function (QueryBuilder $queryBuilder, string $query): void {
                $queryBuilder
                    ->leftJoin('entity.contactDetails', 'contactDetails')
                    ->leftJoin('contactDetails.addressContacts', 'addressContact')
                    ->leftJoin('addressContact.municipality', 'municipality')
                    ->leftJoin('contactDetails.emailContactLinks', 'organizationEmailContactLink')
                    ->leftJoin('organizationEmailContactLink.emailContact', 'emailContact')
                    ->leftJoin('entity.people', 'person')
                    ->leftJoin('person.contactDetails', 'personContactDetails')
                    ->leftJoin('personContactDetails.emailContactLinks', 'personEmailContactLink')
                    ->leftJoin('personEmailContactLink.emailContact', 'personEmailContact')
                    ->andWhere('LOWER(entity.name) LIKE LOWER(:query) OR LOWER(municipality.name) LIKE LOWER(:query) OR LOWER(emailContact.emailAddress) LIKE LOWER(:query) OR LOWER(personEmailContact.emailAddress) LIKE LOWER(:query)')
                    ->setParameter('query', '%' . trim($query) . '%')
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
