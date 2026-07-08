<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\Mailing\MailingDeliveryRecipientStatus;
use App\Infrastructure\Admin\Formatter\ContactDisplayFormatter;
use App\Infrastructure\Doctrine\Entity\MailingDeliveryRecipientEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

/**
 * @extends AbstractCrudController<MailingDeliveryRecipientEntity>
 */
final class MailingDeliveryRecipientCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MailingDeliveryRecipientEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.mailing_delivery_recipient.singular')
            ->setEntityLabelInPlural('address_book.mailing_delivery_recipient.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.mailing_delivery_recipient.page.index')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.mailing_delivery_recipient.page.detail')
            ->setDefaultSort(['queuedAt' => 'DESC', 'id' => 'DESC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['campaignUuid', 'emailAddress', 'displayName', 'status', 'lastError']);
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
            ->add(TextFilter::new('campaignUuid', 'admin.field.campaign_uuid'))
            ->add(ChoiceFilter::new('status', 'admin.field.status')->setChoices($this->statusChoices()))
            ->add(DateTimeFilter::new('queuedAt', 'admin.field.queued_at'))
            ->add(DateTimeFilter::new('sentAt', 'admin.field.sent_at'))
            ->add(DateTimeFilter::new('failedAt', 'admin.field.failed_at'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->onlyOnDetail();
        yield TextField::new('campaignUuid', 'admin.field.campaign_uuid');
        yield TextField::new('emailAddress', 'admin.field.email_address')
            ->formatValue(static fn (mixed $value): string => ContactDisplayFormatter::emailLink($value))
            ->renderAsHtml();
        yield TextField::new('displayName', 'admin.field.display_name');
        yield TextField::new('status', 'admin.field.status');
        yield DateTimeField::new('queuedAt', 'admin.field.queued_at');
        yield DateTimeField::new('dispatchedAt', 'admin.field.dispatched_at')->hideOnIndex();
        yield DateTimeField::new('sentAt', 'admin.field.sent_at');
        yield DateTimeField::new('failedAt', 'admin.field.failed_at')->hideOnIndex();
        yield DateTimeField::new('updatedAt', 'admin.field.updated_at')->hideOnIndex();
        yield TextField::new('lastError', 'admin.field.last_error')->onlyOnDetail();
        yield TextField::new('unsubscribeToken', 'admin.field.unsubscribe_token')->onlyOnDetail();
    }

    /**
     * @return array<string, string>
     */
    private function statusChoices(): array
    {
        return [
            MailingDeliveryRecipientStatus::PENDING->value => MailingDeliveryRecipientStatus::PENDING->value,
            MailingDeliveryRecipientStatus::PROCESSING->value => MailingDeliveryRecipientStatus::PROCESSING->value,
            MailingDeliveryRecipientStatus::SENT->value => MailingDeliveryRecipientStatus::SENT->value,
            MailingDeliveryRecipientStatus::FAILED->value => MailingDeliveryRecipientStatus::FAILED->value,
            MailingDeliveryRecipientStatus::CANCELLED->value => MailingDeliveryRecipientStatus::CANCELLED->value,
        ];
    }
}
