<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Form\Model;

use App\Application\Form\Model\EditMailingCampaignFormModel;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignContent;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class MailingSectionsFormTest extends TestCase
{
    public function testClassifyingFreeSubscriberAudienceAsOutreachIsAFieldError(): void
    {
        $campaign = new MailingCampaign('Internal', 'Subject', 'Title', 'Legacy text', 'default', new NewsletterAudienceFilter(includeFreeSubscribers: true));
        $model = EditMailingCampaignFormModel::fromMailingCampaign($campaign);
        $model->campaignType = MailingCampaignType::OUTREACH;
        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($model);
        self::assertCount(1, $violations);
        self::assertSame('campaignType', $violations[0]->getPropertyPath());
    }

    public function testEditingHiddenSectionPreservesItsBlocksAndImageOwnership(): void
    {
        $campaign = new MailingCampaign('Internal', 'Subject', 'Title', '', 'newsletter', NewsletterAudienceFilter::empty(), campaignType: MailingCampaignType::NEWSLETTER, structuredContent: MailingCampaignContent::fromArray([
            'version' => 1, 'sections' => ['editorial' => ['enabled' => false, 'blocks' => [
                ['id' => 'photo', 'kind' => 'image', 'imagePath' => 'uploads/mailing/content/test.webp', 'imageDecorative' => true],
            ]]],
        ]));
        $model = EditMailingCampaignFormModel::fromMailingCampaign($campaign);
        self::assertTrue($model->usesStructuredContent);
        self::assertFalse($model->sections['editorial']->enabled);
        self::assertSame('uploads/mailing/content/test.webp', $model->sections['editorial']->blocks[0]->imagePath);
        self::assertCount(4, $model->sections);
    }
}
