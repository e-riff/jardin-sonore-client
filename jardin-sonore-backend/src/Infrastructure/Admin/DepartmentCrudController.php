<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Doctrine\Entity\DepartmentEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<DepartmentEntity>
 */
final class DepartmentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DepartmentEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('geography.department.singular')
            ->setEntityLabelInPlural('geography.department.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.department.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.department.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.department.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.department.page.detail')
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->onlyOnDetail();
        yield TextField::new('code', 'admin.field.code');
        yield TextField::new('name', 'admin.field.name');
        yield AssociationField::new('region', 'admin.field.region');
    }
}
