<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Admin\Formatter\ContactDisplayFormatter;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<MunicipalityEntity>
 */
final class MunicipalityCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MunicipalityEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('geography.municipality.singular')
            ->setEntityLabelInPlural('geography.municipality.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.municipality.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.municipality.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.municipality.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.municipality.page.detail')
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield TextField::new('name', 'admin.field.name');
        yield TextField::new('inseeCode', 'admin.field.insee_code');
        yield TextField::new('postalCode', 'admin.field.postal_code');
        yield AssociationField::new('department', 'admin.field.department');
        yield TextField::new('phoneNumber', 'admin.field.phone_number')
            ->formatValue(static fn (mixed $value): string => ContactDisplayFormatter::phoneLink($value))
            ->renderAsHtml()
            ->hideOnForm()
            ->hideOnIndex();
        yield TelephoneField::new('phoneNumber', 'admin.field.phone_number')
            ->setHelp('admin.help.phone_number')
            ->onlyOnForms()
            ->hideOnIndex();
        yield TextField::new('emailAddress', 'admin.field.email_address')
            ->formatValue(static fn (mixed $value): string => ContactDisplayFormatter::emailLink($value))
            ->renderAsHtml()
            ->hideOnForm()
            ->hideOnIndex();
        yield EmailField::new('emailAddress', 'admin.field.email_address')
            ->onlyOnForms()
            ->hideOnIndex();
        yield TextareaField::new('address', 'admin.field.address')->hideOnIndex();
        yield TextField::new('siren', 'admin.field.siren')->hideOnIndex();
        yield TextField::new('siret', 'admin.field.siret')->hideOnIndex();
        yield NumberField::new('centerLatitude', 'admin.field.center_latitude')->onlyOnDetail();
        yield NumberField::new('centerLongitude', 'admin.field.center_longitude')->onlyOnDetail();
        yield ArrayField::new('geoShape', 'admin.field.geo_shape')->hideOnIndex();
    }
}
