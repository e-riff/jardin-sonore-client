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
        self::assertSame('Ajoutez une image à cette zone.', $form->get('introBlocks')->get('1')->get('imageFile')->getErrors(true)->current()->getMessage());
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

    public function testLegacyCampaignOpensAsOneParagraphBlockWithoutLosingLineBreaks(): void
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

        self::assertCount(1, $formModel->introBlocks);
        self::assertSame('Premier paragraphe<br>ligne suivante<br><br>Second paragraphe', $formModel->introBlocks[0]->text);
        self::assertNotNull($form->createView());
    }

    public function testAdjacentParagraphBlocksOpenAsOneEditableBlockAroundAnImage(): void
    {
        $campaign = new MailingCampaign(
            internalTitle: 'Campagne',
            emailSubject: 'Sujet',
            publicTitle: 'Titre',
            mainText: 'Texte',
            templateKey: 'default',
            audienceFilter: NewsletterAudienceFilter::empty(),
            introBlocks: [
                ['id' => 'first', 'kind' => 'paragraph', 'text' => 'Première ligne<br>suite'],
                ['id' => 'second', 'kind' => 'paragraph', 'text' => 'Deuxième paragraphe'],
                ['id' => 'photo', 'kind' => 'image', 'imagePath' => 'uploads/mailing/content/photo.webp', 'imageAlt' => 'Photo'],
                ['id' => 'third', 'kind' => 'paragraph', 'text' => 'Après la photo'],
            ],
        );

        $formModel = EditMailingCampaignFormModel::fromMailingCampaign($campaign);

        self::assertCount(3, $formModel->introBlocks);
        self::assertSame('first', $formModel->introBlocks[0]->id);
        self::assertSame('Première ligne<br>suite<br><br>Deuxième paragraphe', $formModel->introBlocks[0]->text);
        self::assertSame('photo', $formModel->introBlocks[1]->id);
        self::assertSame('Après la photo', $formModel->introBlocks[2]->text);
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

    public function testEditFormAcceptsAnImageUploadInANewSplitBlock(): void
    {
        self::bootKernel(['environment' => 'test']);
        $formModel = new EditMailingCampaignFormModel();
        $existingBlock = new MailingIntroBlockFormModel();
        $existingBlock->id = 'existing-block';
        $formModel->introBlocks = [$existingBlock];
        $form = self::getContainer()->get(FormFactoryInterface::class)->create(EditMailingCampaignType::class, $formModel, ['csrf_protection' => false]);

        self::assertTrue($form->createView()->vars['multipart']);

        $form->handleRequest(Request::create(
            '/mailing/campaign/content',
            'POST',
            [$form->getName() => [
                'internalTitle' => 'Campagne',
                'emailSubject' => 'Sujet',
                'publicTitle' => 'Titre',
                'mainText' => '',
                'templateKey' => 'default',
                'introBlocks' => [
                    ['id' => 'existing-block', 'kind' => 'paragraph', 'text' => 'Texte existant'],
                    [
                        'id' => 'new-image-block',
                        'kind' => 'split',
                        'text' => 'Texte ajouté avec la photo',
                        'imageAlt' => 'Photo de l’atelier',
                        'imageSide' => 'right',
                    ],
                ],
            ]],
            files: [$form->getName() => [
                'introBlocks' => [
                    1 => [
                        'imageFile' => new UploadedFile(__DIR__ . '/../../../../public/images/mailing/default-banner.webp', 'photo.webp', 'image/webp', test: true),
                    ],
                ],
            ]],
        ));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertInstanceOf(UploadedFile::class, $formModel->introBlocks[1]->imageFile);
        self::assertSame('new-image-block', $formModel->introBlocks[1]->id);
    }

    public function testEditFormRemovesAnExistingEmptyParagraphBlock(): void
    {
        self::bootKernel(['environment' => 'test']);
        $formModel = new EditMailingCampaignFormModel();
        $firstBlock = new MailingIntroBlockFormModel();
        $firstBlock->id = 'first-block';
        $firstBlock->text = 'Texte conservé';
        $emptyBlock = new MailingIntroBlockFormModel();
        $emptyBlock->id = 'empty-block';
        $lastBlock = new MailingIntroBlockFormModel();
        $lastBlock->id = 'last-block';
        $lastBlock->text = 'Dernier texte';
        $formModel->introBlocks = [$firstBlock, $emptyBlock, $lastBlock];
        $form = self::getContainer()->get(FormFactoryInterface::class)->create(EditMailingCampaignType::class, $formModel, ['csrf_protection' => false]);

        $form->submit([
            'internalTitle' => 'Campagne',
            'emailSubject' => 'Sujet',
            'publicTitle' => 'Titre',
            'templateKey' => 'default',
            'introBlocks' => [
                ['id' => 'first-block', 'kind' => 'paragraph', 'text' => 'Texte conservé'],
                ['id' => 'last-block', 'kind' => 'paragraph', 'text' => 'Dernier texte'],
            ],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertCount(2, $formModel->introBlocks);
        self::assertSame('first-block', $formModel->introBlocks[0]->id);
        self::assertSame('last-block', $formModel->introBlocks[1]->id);
    }

    public function testEmptyParagraphSubmittedAfterDeletingAnotherBlockDoesNotBlockSave(): void
    {
        self::bootKernel(['environment' => 'test']);
        $formModel = new EditMailingCampaignFormModel();
        $formModel->introBlocks = [new MailingIntroBlockFormModel()];
        $form = self::getContainer()->get(FormFactoryInterface::class)->create(EditMailingCampaignType::class, $formModel, ['csrf_protection' => false]);

        $form->submit([
            'internalTitle' => 'Campagne',
            'emailSubject' => 'Sujet',
            'publicTitle' => 'Titre',
            'templateKey' => 'default',
            'introBlocks' => [
                ['id' => 'kept', 'kind' => 'paragraph', 'text' => 'Texte conservé'],
                ['id' => 'empty', 'kind' => 'paragraph', 'text' => '<br><br>'],
            ],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertCount(1, $formModel->introBlocks);
        self::assertSame('kept', $formModel->introBlocks[0]->id);
    }
}
