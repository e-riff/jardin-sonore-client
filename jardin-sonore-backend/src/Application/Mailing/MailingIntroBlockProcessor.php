<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\MailingIntroImageStorageInterface;
use InvalidArgumentException;
use Throwable;

final readonly class MailingIntroBlockProcessor
{
    public function __construct(private MailingIntroImageStorageInterface $mailingIntroImageStorage)
    {
    }

    /**
     * @param list<MailingIntroBlockInput>    $inputs
     * @param list<array<string, mixed>>|null $existingBlocks
     */
    public function process(array $inputs, ?array $existingBlocks = null): ProcessedMailingIntroBlocks
    {
        $existingImagePaths = [];
        foreach ($existingBlocks ?? [] as $existingBlock) {
            if (isset($existingBlock['id'], $existingBlock['imagePath'])
                && is_string($existingBlock['id']) && is_string($existingBlock['imagePath'])) {
                $existingImagePaths[$existingBlock['id']] = $existingBlock['imagePath'];
            }
        }

        $blocks = [];
        $newImagePaths = [];
        $retainedImagePaths = [];
        $seenIds = [];

        try {
            foreach ($inputs as $input) {
                if (1 !== preg_match('/^[a-zA-Z0-9-]{1,64}$/', $input->id)
                    || isset($seenIds[$input->id])
                    || !in_array($input->kind, ['paragraph', 'image', 'split'], true)) {
                    throw new InvalidArgumentException('Invalid mailing introduction block.');
                }

                $seenIds[$input->id] = true;
                $block = ['id' => $input->id, 'kind' => $input->kind];

                if (in_array($input->kind, ['paragraph', 'split'], true)) {
                    $block['text'] = $input->text;
                }

                if (in_array($input->kind, ['image', 'split'], true)) {
                    $imagePath = null === $input->imageFile
                        ? ($existingImagePaths[$input->id] ?? null)
                        : $this->mailingIntroImageStorage->store($input->imageFile);

                    if (null === $imagePath) {
                        throw new InvalidArgumentException('Mailing introduction image is required.');
                    }

                    if (null !== $input->imageFile) {
                        $newImagePaths[] = $imagePath;
                    }

                    $retainedImagePaths[$imagePath] = true;
                    $block['imagePath'] = $imagePath;
                    $block['imageAlt'] = $input->imageDecorative ? '' : trim($input->imageAlt ?? '');
                    $block['imageDecorative'] = $input->imageDecorative;

                    if (!$input->imageDecorative && '' === $block['imageAlt']) {
                        throw new InvalidArgumentException('Mailing introduction image needs alternative text.');
                    }
                }

                if ('split' === $input->kind) {
                    if (!in_array($input->imageSide, ['left', 'right'], true)) {
                        throw new InvalidArgumentException('Invalid mailing introduction image position.');
                    }

                    $block['imageSide'] = $input->imageSide;
                }

                $blocks[] = $block;
            }
        } catch (Throwable $throwable) {
            foreach ($newImagePaths as $imagePath) {
                $this->mailingIntroImageStorage->delete($imagePath);
            }

            throw $throwable;
        }

        $obsoleteImagePaths = array_values(array_filter(
            $existingImagePaths,
            static fn (string $imagePath): bool => !isset($retainedImagePaths[$imagePath]),
        ));

        return new ProcessedMailingIntroBlocks($blocks, $newImagePaths, $obsoleteImagePaths);
    }

    /** @param list<array<string, mixed>> $blocks */
    public static function plainText(array $blocks): string
    {
        $paragraphs = [];
        foreach ($blocks as $block) {
            if (!in_array($block['kind'] ?? null, ['paragraph', 'split'], true)) {
                continue;
            }

            $textWithBreaks = preg_replace('/<br\s*\/?>/i', ' ', (string) ($block['text'] ?? ''));
            $text = html_entity_decode(strip_tags($textWithBreaks ?? ''), ENT_QUOTES | ENT_HTML5);
            $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
            if ('' !== $text) {
                $paragraphs[] = $text;
            }
        }

        return implode("\n\n", $paragraphs);
    }
}
