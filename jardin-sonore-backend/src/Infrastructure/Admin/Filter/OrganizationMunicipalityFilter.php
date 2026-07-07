<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Filter;

use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\EntityFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use Symfony\Contracts\Translation\TranslatableInterface;

final class OrganizationMunicipalityFilter implements FilterInterface
{
    use FilterTrait;

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(string $propertyName = 'organizationMunicipality', $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(EntityFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle')
            ->setFormTypeOption('value_type_options.class', MunicipalityEntity::class)
            ->setFormTypeOption('value_type_options.multiple', false);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $comparison = $filterDataDto->getComparison();
        $parameterName = $filterDataDto->getParameterName();
        $value = $filterDataDto->getValue();

        $aliases = $queryBuilder->getAllAliases();

        if (!in_array('orgFilterContactDetails', $aliases, true)) {
            $queryBuilder->leftJoin('entity.contactDetails', 'orgFilterContactDetails');
        }

        if (!in_array('orgFilterAddressContact', $aliases, true)) {
            $queryBuilder->leftJoin('orgFilterContactDetails.addressContacts', 'orgFilterAddressContact');
        }

        if (!in_array('orgFilterMunicipality', $aliases, true)) {
            $queryBuilder->leftJoin('orgFilterAddressContact.municipality', 'orgFilterMunicipality');
        }

        if (null === $value) {
            $queryBuilder->andWhere(sprintf('orgFilterMunicipality %s', $comparison));

            return;
        }

        $orX = new Orx();
        $orX->add(sprintf('orgFilterMunicipality %s (:%s)', $comparison, $parameterName));

        if (ComparisonType::NEQ === $comparison) {
            $orX->add('orgFilterMunicipality IS NULL');
        }

        $queryBuilder
            ->andWhere($orX)
            ->setParameter($parameterName, $value);
    }
}
