<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mailing;

use App\Application\Mailing\MailingIntroBlockInput;
use App\Application\Mailing\MailingIntroBlockProcessor;
use App\Application\Storage\MailingIntroImageStorageInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MailingIntroBlockProcessorTest extends TestCase
{
    public function testItReusesOnlyTheImageBelongingToTheSubmittedBlockId(): void
    {
        $storage = $this->storage();
        $processor = new MailingIntroBlockProcessor($storage);
        $existing = [
            ['id' => 'photo', 'kind' => 'image', 'imagePath' => 'uploads/mailing/content/original.webp', 'imageAlt' => 'Avant', 'imageDecorative' => false],
        ];

        $result = $processor->process([
            new MailingIntroBlockInput('text', 'paragraph', 'Bonjour', null, null, false, 'right'),
            new MailingIntroBlockInput('photo', 'image', '', null, 'Après', false, 'right'),
        ], $existing);

        self::assertSame('uploads/mailing/content/original.webp', $result->blocks[1]['imagePath']);
        self::assertSame([], $result->obsoleteImagePaths);
    }

    public function testItDoesNotAcceptAForgedImageReference(): void
    {
        $processor = new MailingIntroBlockProcessor($this->storage());

        $this->expectException(InvalidArgumentException::class);

        $processor->process([
            new MailingIntroBlockInput('unknown', 'image', '', null, 'Photo', false, 'right'),
        ], [['id' => 'photo', 'kind' => 'image', 'imagePath' => 'uploads/mailing/content/original.webp']]);
    }

    public function testItMarksReplacedAndRemovedImagesForDeletionAfterSave(): void
    {
        $storage = $this->storage();
        $processor = new MailingIntroBlockProcessor($storage);
        $upload = new UploadedFile(__DIR__ . '/../../../../public/images/mailing/default-banner.webp', 'photo.webp', 'image/webp', test: true);

        $result = $processor->process([
            new MailingIntroBlockInput('one', 'image', '', $upload, 'Nouvelle photo', false, 'right'),
            new MailingIntroBlockInput('text', 'paragraph', 'Bonjour', null, null, false, 'right'),
        ], [
            ['id' => 'one', 'kind' => 'image', 'imagePath' => 'uploads/mailing/content/old.webp'],
            ['id' => 'two', 'kind' => 'image', 'imagePath' => 'uploads/mailing/content/removed.webp'],
        ]);

        self::assertSame(['uploads/mailing/content/old.webp', 'uploads/mailing/content/removed.webp'], $result->obsoleteImagePaths);
        self::assertSame(['uploads/mailing/content/new.webp'], $result->newImagePaths);
    }

    public function testItRemovesNewUploadsIfAFollowingBlockIsInvalid(): void
    {
        $storage = new class implements MailingIntroImageStorageInterface {
            /** @var list<string> */
            public array $deletedPaths = [];

            public function store(UploadedFile $uploadedFile): string
            {
                return 'uploads/mailing/content/new.webp';
            }

            public function delete(string $storedPath): void
            {
                $this->deletedPaths[] = $storedPath;
            }
        };
        $processor = new MailingIntroBlockProcessor($storage);
        $upload = new UploadedFile(__DIR__ . '/../../../../public/images/mailing/default-banner.webp', 'photo.webp', 'image/webp', test: true);

        try {
            $processor->process([
                new MailingIntroBlockInput('one', 'image', '', $upload, 'Photo', false, 'right'),
                new MailingIntroBlockInput('one', 'paragraph', 'Doublon', null, null, false, 'right'),
            ]);
            self::fail('Duplicate block identifiers should be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame(['uploads/mailing/content/new.webp'], $storage->deletedPaths);
        }
    }

    public function testCompatibilityTextKeepsLineBreakSeparation(): void
    {
        self::assertSame('Bonjour vous', MailingIntroBlockProcessor::plainText([
            ['kind' => 'paragraph', 'text' => 'Bonjour<br>vous'],
        ]));
    }

    private function storage(): MailingIntroImageStorageInterface
    {
        return new class implements MailingIntroImageStorageInterface {
            public function store(UploadedFile $uploadedFile): string
            {
                return 'uploads/mailing/content/new.webp';
            }

            public function delete(string $storedPath): void
            {
            }
        };
    }
}
