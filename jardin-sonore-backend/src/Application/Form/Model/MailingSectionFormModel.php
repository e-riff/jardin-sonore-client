<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Application\Mailing\MailingIntroBlockInput;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class MailingSectionFormModel
{
    public bool $enabled = false;

    /** @var list<MailingIntroBlockFormModel> */
    #[Assert\Valid]
    public array $blocks = [];

    #[Assert\Callback]
    public function validateBlocks(ExecutionContextInterface $context): void
    {
        $seenIds = [];
        foreach ($this->blocks as $index => $block) {
            if (1 !== preg_match('/^[a-zA-Z0-9-]{1,64}$/', $block->id) || isset($seenIds[$block->id])
                || !in_array($block->kind, ['paragraph', 'image', 'split'], true)) {
                $context->buildViolation('mailing.form.intro_block_invalid')->atPath("blocks[{$index}]")->addViolation();
            }
            $seenIds[$block->id] = true;
            if (in_array($block->kind, ['paragraph', 'split'], true) && '' === trim(strip_tags($block->text))) {
                $context->buildViolation('mailing.form.intro_text_required')->atPath("blocks[{$index}].text")->addViolation();
            }
            if (in_array($block->kind, ['image', 'split'], true)) {
                if (null === $block->imageFile && null === $block->imagePath) {
                    $context->buildViolation('mailing.form.intro_image_required')->atPath("blocks[{$index}].imageFile")->addViolation();
                }
                if (!$block->imageDecorative && '' === trim($block->imageAlt ?? '')) {
                    $context->buildViolation('mailing.form.intro_alt_required')->atPath("blocks[{$index}].imageAlt")->addViolation();
                }
            }
            if ('split' === $block->kind && !in_array($block->imageSide, ['left', 'right'], true)) {
                $context->buildViolation('mailing.form.intro_block_invalid')->atPath("blocks[{$index}].imageSide")->addViolation();
            }
        }
    }

    /** @return array{enabled: bool, blocks: list<MailingIntroBlockInput>} */
    public function toInput(): array
    {
        return ['enabled' => $this->enabled, 'blocks' => array_map(static fn (MailingIntroBlockFormModel $block): MailingIntroBlockInput => $block->toInput(), $this->blocks)];
    }
}
