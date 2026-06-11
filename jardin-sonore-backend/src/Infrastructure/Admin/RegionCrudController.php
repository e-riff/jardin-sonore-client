<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Doctrine\Entity\RegionEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<RegionEntity>
 */
final class RegionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return RegionEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('geography.region.singular')
            ->setEntityLabelInPlural('geography.region.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.region.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.region.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.region.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.region.page.detail');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield TextField::new('code', 'admin.field.code');
        yield TextField::new('name', 'admin.field.name');
    }
}
