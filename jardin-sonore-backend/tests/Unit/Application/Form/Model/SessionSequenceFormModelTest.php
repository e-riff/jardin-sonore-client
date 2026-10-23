<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Form\Model;

use App\Application\Form\Model\SessionSequenceFormModel;
use App\Application\Session\MediaResourceView;
use App\Application\Session\RepertoireItemView;
use App\Domain\Model\Session\MediaResource;
use App\Domain\Model\Session\MediaResourceType;
use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use PHPUnit\Framework\TestCase;

final class SessionSequenceFormModelTest extends TestCase
{
    public function testTitleCanBeMissingWhileAnActivityDraftOpensTheMediaCatalog(): void
    {
        $formModel = file_get_contents(__DIR__ . '/../../../../../src/Application/Form/Model/SessionSequenceFormModel.php');

        self::assertIsString($formModel);
        self::assertStringContainsString("public ?string \$title = '';", $formModel);
    }

    public function testRepertoireSequenceKeepsSourceDetailsInTheRepertoire(): void
    {
        $repertoireItem = new RepertoireItem(
            type: RepertoireItemType::NURSERY_RHYME,
            title: 'Brrr, brr, il fait froid',
            generalInstructions: 'Consigne du répertoire.',
        );

        $sessionSequenceFormModel = SessionSequenceFormModel::fromRepertoireItemView(RepertoireItemView::fromDomain($repertoireItem));

        self::assertSame('', $sessionSequenceFormModel->body);
        self::assertSame($repertoireItem->getUuid()->toRfc4122(), $sessionSequenceFormModel->sourceUuid);
    }

    public function testAddingCatalogMediaCopiesItsDisplayDataIntoTheActivity(): void
    {
        $mediaResource = new MediaResource(
            type: MediaResourceType::VIDEO,
            title: 'Comptine des vagues',
            primaryUrl: 'https://www.youtube.com/watch?v=waves',
            imageUrl: 'https://example.test/waves.jpg',
        );
        $sessionSequenceFormModel = new SessionSequenceFormModel();

        $sessionSequenceFormModel->addMediaResource(MediaResourceView::fromDomain($mediaResource));

        self::assertCount(1, $sessionSequenceFormModel->media);
        self::assertSame('Comptine des vagues', $sessionSequenceFormModel->media[0]->label);
        self::assertSame(MediaResourceType::VIDEO, $sessionSequenceFormModel->media[0]->type);
        self::assertSame('https://www.youtube.com/watch?v=waves', $sessionSequenceFormModel->media[0]->url);
        self::assertSame('https://example.test/waves.jpg', $sessionSequenceFormModel->media[0]->imageUrl);
        self::assertTrue($sessionSequenceFormModel->media[0]->featured);
    }

    public function testCreatingASequenceFromCatalogMediaKeepsTheMediaAsTheFeaturedSessionMedia(): void
    {
        $mediaResource = new MediaResource(
            type: MediaResourceType::VIDEO,
            title: 'Comptine des vagues',
            primaryUrl: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            imageUrl: 'https://example.test/waves.jpg',
        );

        $sessionSequenceFormModel = SessionSequenceFormModel::fromMediaResourceView(MediaResourceView::fromDomain($mediaResource));

        self::assertCount(1, $sessionSequenceFormModel->media);
        self::assertSame('Comptine des vagues', $sessionSequenceFormModel->media[0]->label);
        self::assertSame(MediaResourceType::VIDEO, $sessionSequenceFormModel->media[0]->type);
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $sessionSequenceFormModel->media[0]->url);
        self::assertSame('https://example.test/waves.jpg', $sessionSequenceFormModel->media[0]->imageUrl);
        self::assertTrue($sessionSequenceFormModel->media[0]->featured);
    }

    public function testRemovingASelectedCatalogMediumDoesNotRemoveLegacySessionMedia(): void
    {
        $catalogMedia = new MediaResource(
            type: MediaResourceType::VIDEO,
            title: 'Vidéo liée',
            primaryUrl: 'https://example.test/video',
        );
        $sessionSequenceFormModel = new SessionSequenceFormModel();
        $sessionSequenceFormModel->addMediaResource(MediaResourceView::fromDomain($catalogMedia));
        $legacyMedia = new \App\Application\Form\Model\SessionSequenceMediaFormModel();
        $legacyMedia->label = 'Ancien lien';
        $legacyMedia->url = 'https://example.test/legacy';
        $sessionSequenceFormModel->media[] = $legacyMedia;
        $sessionSequenceFormModel->catalogMediaUuids = [];

        $sessionSequenceFormModel->retainSelectedCatalogMedia();

        self::assertCount(1, $sessionSequenceFormModel->media);
        self::assertSame('Ancien lien', $sessionSequenceFormModel->media[0]->label);
    }
}
