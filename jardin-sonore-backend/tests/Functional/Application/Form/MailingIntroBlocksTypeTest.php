<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Form;

use App\Application\Form\CreateMailingCampaignType;
use App\Application\Form\EditMailingCampaignType;
use App\Application\Form\MailingIntroBlockType;
use App\Application\Form\Model\CreateMailingCampaignFormModel;
use App\Application\Form\Model\EditMailingCampaignFormModel;
use App\Application\Form\Model\MailingIntroBlockFormModel;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

final class MailingIntroBlocksTypeTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testNewFormAcceptsOrderedBlocksAndAnOptionalPreheader(): void
    {
        self::bootKernel(['environment' => 'test']);
        $formModel = new CreateMailingCampaignFormModel();
        $form = self::getContainer()->get(FormFactoryInterface::class)->create(CreateMailingCampaignType::class, $formModel);
        $form->submit([
            'internalTitle' => 'Campagne',
            'emailSubject' => 'Sujet',
            'publicTitle' => 'Titre',
            'preheader' => 'Aperçu Gmail',
            'introBlocks' => [
                ['id' => 'first', 'kind' => 'paragraph', 'text' => 'Bonjour <strong>vous</strong>'],
                ['id' => 'second', 'kind' => 'image', 'imageAlt' => 'Photo'],
            ],
        ]);

        self::assertFalse($form->isValid());
        self::assertSame('Aperçu Gmail', $formModel->preheader);
        self::assertCount(2, $formModel->introBlocks);
        self::assertSame('second', $formModel->introBlocks[1]->id);
        self::assertGreaterThan(0, count($form->get('introBlocks')->get('1')->get('imageFile')->getErrors(true)));
    }

    public function testNewFormRendersAndAcceptsSeveralTextBlocks(): void
    {
        self::bootKernel(['environment' => 'test']);
        $formModel = new CreateMailingCampaignFormModel();
        $form = self::getContainer()->get(FormFactoryInterface::class)->create(CreateMailingCampaignType::class, $formModel, ['csrf_protection' => false]);
        self::getContainer()->get(RequestStack::class)->push(Request::create('/mailing/new'));
        $html = self::getContainer()->get(Environment::class)->render('mailing/new.html.twig', ['form' => $form->createView()]);

        self::assertStringContainsString('data-controller="mailing-intro"', $html);
        self::assertStringContainsString('data-prototype=', $html);

        $form->submit([
            'internalTitle' => 'Campagne',
            'emailSubject' => 'Sujet',
            'publicTitle' => 'Titre',
            'preheader' => '',
            'mainText' => '',
            'introBlocks' => [
                ['id' => 'first', 'kind' => 'paragraph', 'text' => 'Bonjour <strong>vous</strong>'],
                ['id' => 'second', 'kind' => 'paragraph', 'text' => 'La suite'],
                [
                    'id' => 'third',
                    'kind' => 'split',
                    'text' => 'Texte illustré',
                    'imageFile' => new UploadedFile(__DIR__ . '/../../../../public/images/mailing/default-banner.webp', 'photo.webp', 'image/webp', test: true),
                    'imageAlt' => 'Photo de l’atelier',
                    'imageSide' => 'left',
                ],
            ],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertSame(['first', 'second', 'third'], array_map(static fn ($block): string => $block->id, $formModel->introBlocks));
        self::assertSame('left', $formModel->introBlocks[2]->imageSide);
        self::assertNull($formModel->preheader);
    }

    public function testLegacyCampaignOpensAsParagraphBlocksWithoutLosingItsText(): void
    {
        self::bootKernel(['environment' => 'test']);
        $campaign = new MailingCampaign(
            internalTitle: 'Ancien',
            emailSubject: 'Sujet',
            publicTitle: 'Titre',
            mainText: "Premier paragraphe\nligne suivante\n\nSecond paragraphe",
            templateKey: 'default',
            audienceFilter: NewsletterAudienceFilter::empty(),
        );
        $formModel = EditMailingCampaignFormModel::fromMailingCampaign($campaign);
        $form = self::getContainer()->get(FormFactoryInterface::class)->create(EditMailingCampaignType::class, $formModel);

        self::assertCount(2, $formModel->introBlocks);
        self::assertSame('Premier paragraphe<br>ligne suivante', $formModel->introBlocks[0]->text);
        self::assertSame('Second paragraphe', $formModel->introBlocks[1]->text);
        self::assertNotNull($form->createView());
    }

    public function testAnEmptySubmittedBlockIdKeepsTheExistingIdentifier(): void
    {
        self::bootKernel(['environment' => 'test']);
        $block = new MailingIntroBlockFormModel();
        $block->id = 'existing-block';
        $form = self::getContainer()->get(FormFactoryInterface::class)->create(MailingIntroBlockType::class, $block);

        $form->submit([
            'id' => '',
            'kind' => 'paragraph',
            'text' => 'Texte conservé',
            'imageFile' => '',
            'imageAlt' => '',
            'imageDecorative' => '',
            'imageSide' => 'right',
        ]);

        self::assertSame('existing-block', $block->id);
    }

    public function testNullSubmittedBlockIdAndKindKeepTheirExistingValues(): void
    {
        self::bootKernel(['environment' => 'test']);
        $block = new MailingIntroBlockFormModel();
        $block->id = 'existing-block';
        $block->kind = 'paragraph';
        $form = self::getContainer()->get(FormFactoryInterface::class)->create(MailingIntroBlockType::class, $block);

        $form->submit([
            'id' => null,
            'kind' => null,
            'text' => 'Texte conservé',
            'imageFile' => '',
            'imageAlt' => '',
            'imageDecorative' => '',
            'imageSide' => 'right',
        ]);

        self::assertSame('existing-block', $block->id);
        self::assertSame('paragraph', $block->kind);
    }
}
