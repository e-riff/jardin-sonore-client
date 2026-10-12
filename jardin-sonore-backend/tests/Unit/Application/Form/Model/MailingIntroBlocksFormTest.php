<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Form\Model;

use App\Application\Form\Model\CreateMailingCampaignFormModel;
use App\Application\Form\Model\EditMailingCampaignFormModel;
use App\Application\Form\Model\MailingIntroBlockFormModel;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class MailingIntroBlocksFormTest extends TestCase
{
    public function testNewImageBlockRequiresAnUploadAndAlternativeText(): void
    {
        $formModel = new CreateMailingCampaignFormModel();
        $formModel->internalTitle = 'Campagne';
        $formModel->emailSubject = 'Objet';
        $formModel->publicTitle = 'Titre';
        $formModel->introBlocks[0]->text = 'Bonjour';
        $imageBlock = new MailingIntroBlockFormModel();
        $imageBlock->kind = 'image';
        $formModel->introBlocks[] = $imageBlock;

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($formModel);

        self::assertSame(2, count($violations));
    }

    public function testOldDraftIsPresentedAsTextBlockWithoutChangingItsDomainContent(): void
    {
        $campaign = new MailingCampaign(
            internalTitle: 'Ancienne',
            emailSubject: 'Objet',
            publicTitle: 'Titre',
            mainText: 'Texte <strong>historique</strong>',
            templateKey: 'default',
            audienceFilter: NewsletterAudienceFilter::empty(),
        );

        $formModel = EditMailingCampaignFormModel::fromMailingCampaign($campaign);

        self::assertSame('Texte <strong>historique</strong>', $formModel->introBlocks[0]->text);
        self::assertNull($campaign->getIntroBlocks());
    }

    public function testRemovingEveryBlockIsRejected(): void
    {
        $formModel = new CreateMailingCampaignFormModel();
        $formModel->internalTitle = 'Campagne';
        $formModel->emailSubject = 'Objet';
        $formModel->publicTitle = 'Titre';
        $formModel->mainText = 'Ancien texte';
        $formModel->introBlocks = [];

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($formModel);

        self::assertCount(1, $violations);
        self::assertSame('introBlocks', $violations[0]->getPropertyPath());
    }
}
