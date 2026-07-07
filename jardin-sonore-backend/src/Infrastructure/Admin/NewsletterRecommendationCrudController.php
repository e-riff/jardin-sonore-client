<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Infrastructure\Doctrine\Entity\NewsletterRecommendationEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

/**
 * @extends AbstractCrudController<NewsletterRecommendationEntity>
 */
final class NewsletterRecommendationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NewsletterRecommendationEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.recommendation.singular')
            ->setEntityLabelInPlural('address_book.recommendation.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.newsletter_recommendation.page.index')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.newsletter_recommendation.page.detail')
            ->setDefaultSort(['active' => 'DESC', 'updatedAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['title', 'tag', 'text', 'url', 'linkLabel']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('active', 'admin.field.active'))
            ->add(TextFilter::new('tag', 'admin.field.tag'))
            ->add(DateTimeFilter::new('updatedAt', 'admin.field.updated_at'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->onlyOnDetail();
        yield TextField::new('uuid', 'admin.field.uuid')->onlyOnDetail();
        yield TextField::new('title', 'admin.field.title');
        yield TextField::new('tag', 'admin.field.tag');
        yield UrlField::new('url', 'admin.field.url')->hideOnIndex();
        yield TextField::new('linkLabel', 'admin.field.link_label')->hideOnIndex();
        yield BooleanField::new('active', 'admin.field.active');
        yield DateTimeField::new('updatedAt', 'admin.field.updated_at');
        yield DateTimeField::new('createdAt', 'admin.field.created_at')->onlyOnDetail();
        yield TextField::new('imagePath', 'admin.field.image_path')->hideOnIndex();
        yield TextEditorField::new('text', 'admin.field.text')->onlyOnDetail();
    }
}
