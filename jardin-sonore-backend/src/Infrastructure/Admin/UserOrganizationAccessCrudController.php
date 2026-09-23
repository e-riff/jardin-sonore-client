<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Application\Portal\PortalAccessManager;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

/** @extends AbstractCrudController<UserOrganizationAccessEntity> */
final class UserOrganizationAccessCrudController extends AbstractCrudController
{
    public function __construct(private readonly PortalAccessManager $portalAccessManager)
    {
    }

    public static function getEntityFqcn(): string
    {
        return UserOrganizationAccessEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Accès structure')
            ->setEntityLabelInPlural('Accès structures')
            ->setDefaultSort(['organization.name' => 'ASC'])
            ->setSearchFields(['organization.name', 'user.email', 'person.firstName', 'person.lastName']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('organization', 'Structure')->autocomplete())
            ->add(BooleanFilter::new('active', 'Actif'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield TextField::new('organization', 'Structure')
            ->onlyOnIndex()
            ->formatValue(static function (mixed $value, ?UserOrganizationAccessEntity $userOrganizationAccessEntity): string {
                if (!$userOrganizationAccessEntity instanceof UserOrganizationAccessEntity) {
                    return '';
                }

                $organizationEntity = $userOrganizationAccessEntity->getOrganization();
                $municipality = $organizationEntity->getMunicipalitySummary();

                return '—' === $municipality
                    ? htmlspecialchars($organizationEntity->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    : htmlspecialchars($organizationEntity->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . ' <em class="text-muted">' . htmlspecialchars($municipality, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</em>';
            })
            ->renderAsHtml();
        yield AssociationField::new('user', 'Compte')->autocomplete()->hideOnForm();
        yield AssociationField::new('organization', 'Structure')
            ->autocomplete(true, static function (OrganizationEntity $organizationEntity): string {
                $municipality = $organizationEntity->getMunicipalitySummary();

                return '—' === $municipality ? $organizationEntity->getName() : "{$organizationEntity->getName()} — {$municipality}";
            })
            ->setSortProperty('name')
            ->hideOnIndex();
        yield AssociationField::new('person', 'Personne')->autocomplete()->hideOnForm();
        yield BooleanField::new('active', 'Actif')->hideOnForm();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if ($entityInstance instanceof UserOrganizationAccessEntity) {
            $this->portalAccessManager->removeAccess($entityInstance->getUser(), $entityInstance);
            $entityManager->flush();

            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
