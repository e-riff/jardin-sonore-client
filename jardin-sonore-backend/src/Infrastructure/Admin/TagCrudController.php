<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Doctrine\Entity\TagEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<TagEntity>
 */
final class TagCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TagEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.tag.singular')
            ->setEntityLabelInPlural('address_book.tag.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.tag.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.tag.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.tag.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.tag.page.detail');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield TextField::new('label', 'admin.field.label');
        yield AssociationField::new('organizations', 'admin.field.organizations')->onlyOnDetail();
    }
}
