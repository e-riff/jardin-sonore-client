<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Application\Portal\PortalAccessManager;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

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
        return $crud->setEntityLabelInSingular('Accès structure')->setEntityLabelInPlural('Accès structures');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield AssociationField::new('user', 'Compte')->autocomplete()->hideOnForm();
        yield AssociationField::new('organization', 'Structure')->autocomplete();
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
