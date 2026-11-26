<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\MailingIntroImageStorageInterface;
use App\Domain\Model\Mailing\MailingCampaignContent;
use Throwable;

final readonly class MailingStructuredContentProcessor
{
    public function __construct(private MailingIntroBlockProcessor $blockProcessor, private MailingIntroImageStorageInterface $imageStorage)
    {
    }

    /**
     * @param array<string, array{enabled: bool, blocks: list<MailingIntroBlockInput>}> $sections
     *
     * @return array{content: MailingCampaignContent, newImages: list<string>, obsoleteImages: list<string>}
     */
    public function process(array $sections, ?MailingCampaignContent $previous): array
    {
        $result = [];
        $newImages = [];
        $obsoleteImages = [];
        try {
            foreach ($sections as $key => $section) {
                $processed = $this->blockProcessor->process($section['blocks'], $previous?->getBlocks($key));
                array_push($newImages, ...$processed->newImagePaths);
                array_push($obsoleteImages, ...$processed->obsoleteImagePaths);
                $result[$key] = ['enabled' => $section['enabled'], 'blocks' => $processed->blocks];
            }
            $content = MailingCampaignContent::fromArray(['version' => 1, 'sections' => $result]);
        } catch (Throwable $throwable) {
            foreach ($newImages as $path) {
                $this->imageStorage->delete($path);
            }
            throw $throwable;
        }

        return ['content' => $content, 'newImages' => $newImages, 'obsoleteImages' => $obsoleteImages];
    }
}
