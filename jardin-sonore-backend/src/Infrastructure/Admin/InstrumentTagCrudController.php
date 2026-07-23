<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Doctrine\Entity\InstrumentTagEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<InstrumentTagEntity>
 */
final class InstrumentTagCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return InstrumentTagEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('content_catalog.instrument_tag.singular')
            ->setEntityLabelInPlural('content_catalog.instrument_tag.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.instrument_tag.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.instrument_tag.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.instrument_tag.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.instrument_tag.page.detail')
            ->setSearchFields(['label'])
            ->setDefaultSort(['label' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield TextField::new('label', 'admin.field.label');
        yield ColorField::new('color', 'admin.field.color');
        yield AssociationField::new('instruments', 'admin.field.instruments')->onlyOnDetail();
    }
}
