<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Doctrine\Entity\InstrumentEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

/**
 * @extends AbstractCrudController<InstrumentEntity>
 */
final class InstrumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return InstrumentEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('content_catalog.instrument.singular')
            ->setEntityLabelInPlural('content_catalog.instrument.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.instrument.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.instrument.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.instrument.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.instrument.page.detail')
            ->setSearchFields(['name', 'tuning', 'notes', 'tags.label'])
            ->setDefaultSort(['name' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'admin.field.name'))
            ->add(TextFilter::new('tuning', 'admin.field.tuning'))
            ->add(NumericFilter::new('quantity', 'admin.field.quantity'))
            ->add(EntityFilter::new('tags', 'admin.field.tags')->canSelectMultiple()->autocomplete())
            ->add(BooleanFilter::new('active', 'admin.field.active'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield TextField::new('name', 'admin.field.name');
        yield TextField::new('tuning', 'admin.field.tuning');
        yield IntegerField::new('quantity', 'admin.field.quantity');
        yield AssociationField::new('tags', 'admin.field.tags')->autocomplete()->hideOnIndex();
        yield TextField::new('tagsSummary', 'admin.field.tags')->onlyOnIndex();
        yield TextareaField::new('notes', 'admin.field.notes')->hideOnIndex();
        yield BooleanField::new('active', 'admin.field.active');
        yield DateTimeField::new('updatedAt', 'admin.field.updated_at')->hideOnForm();
        yield DateTimeField::new('createdAt', 'admin.field.created_at')->onlyOnDetail();
    }
}
