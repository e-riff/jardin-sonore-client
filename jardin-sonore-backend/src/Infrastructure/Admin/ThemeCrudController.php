<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Doctrine\Entity\ThemeEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<ThemeEntity> */
final class ThemeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ThemeEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('content_catalog.theme.singular')->setEntityLabelInPlural('content_catalog.theme.plural')->setSearchFields(['label'])->setDefaultSort(['label' => 'ASC'])->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield TextField::new('label', 'admin.field.label');
        yield ColorField::new('color', 'admin.field.color');
    }
}
