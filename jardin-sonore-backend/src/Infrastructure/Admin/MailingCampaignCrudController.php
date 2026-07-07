<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Infrastructure\Admin\Formatter\ContactDisplayFormatter;
use App\Infrastructure\Doctrine\Entity\MailingCampaignEntity;
use BackedEnum;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<MailingCampaignEntity>
 */
final class MailingCampaignCrudController extends AbstractCrudController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getEntityFqcn(): string
    {
        return MailingCampaignEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.mailing_campaign.singular')
            ->setEntityLabelInPlural('address_book.mailing_campaign.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.mailing_campaign.page.index')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.mailing_campaign.page.detail')
            ->setDefaultSort(['status' => 'ASC', 'updatedAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['internalTitle', 'emailSubject', 'publicTitle', 'templateKey']);
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
            ->add(ChoiceFilter::new('status', 'admin.field.status')->setChoices($this->statusChoices())->setFormTypeOption('value_type_options.translation_domain', 'mailing'))
            ->add(DateTimeFilter::new('lastTestSentAt', 'admin.field.last_test_sent_at'))
            ->add(DateTimeFilter::new('updatedAt', 'admin.field.updated_at'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->onlyOnDetail();
        yield TextField::new('uuid', 'admin.field.uuid')->onlyOnDetail();
        yield TextField::new('internalTitle', 'admin.field.internal_title');
        yield TextField::new('emailSubject', 'admin.field.email_subject');
        yield TextField::new('publicTitle', 'admin.field.public_title')->hideOnIndex();
        yield ChoiceField::new('status', 'admin.field.status')
            ->setChoices($this->statusChoices())
            ->formatValue(fn (mixed $value): string => $this->translateEnumValue('mailing.status', $value));
        yield IntegerField::new('recommendationCount', 'admin.field.recommendation_count');
        yield IntegerField::new('activeRecommendationCount', 'admin.field.active_recommendation_count')->hideOnDetail();
        yield BooleanField::new('hasAudienceCriteria', 'admin.field.has_audience_criteria');
        yield DateTimeField::new('lastTestSentAt', 'admin.field.last_test_sent_at');
        yield DateTimeField::new('updatedAt', 'admin.field.updated_at')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'admin.field.created_at')->onlyOnDetail();
        yield TextField::new('templateKey', 'admin.field.template_key')->onlyOnDetail();
        yield TextField::new('recommendationsSummary', 'admin.field.recommendations')
            ->formatValue(static fn (mixed $value): string => ContactDisplayFormatter::textSummary($value))
            ->renderAsHtml()
            ->onlyOnDetail();
        yield TextField::new('audienceFilterJson', 'admin.field.audience_filter')
            ->formatValue(static fn (mixed $value): string => sprintf('<pre style="white-space:pre-wrap">%s</pre>', htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')))
            ->renderAsHtml()
            ->onlyOnDetail();
    }

    /**
     * @return array<string, MailingCampaignStatus>
     */
    private function statusChoices(): array
    {
        return [
            'mailing.status.draft' => MailingCampaignStatus::DRAFT,
            'mailing.status.ready_for_test' => MailingCampaignStatus::READY_FOR_TEST,
            'mailing.status.test_sent' => MailingCampaignStatus::TEST_SENT,
            'mailing.status.delivery_queued' => MailingCampaignStatus::DELIVERY_QUEUED,
            'mailing.status.delivery_sending' => MailingCampaignStatus::DELIVERY_SENDING,
            'mailing.status.delivery_stopped' => MailingCampaignStatus::DELIVERY_STOPPED,
            'mailing.status.delivery_sent' => MailingCampaignStatus::DELIVERY_SENT,
            'mailing.status.delivery_failed' => MailingCampaignStatus::DELIVERY_FAILED,
        ];
    }

    private function translateEnumValue(string $translationPrefix, mixed $value): string
    {
        return $value instanceof BackedEnum ? $this->translator->trans("{$translationPrefix}.{$value->value}", [], 'mailing') : '';
    }
}
