<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

trait MailingIntroBlocksFormTrait
{
    /** @var list<MailingIntroBlockFormModel> */
    #[Assert\Valid]
    public array $introBlocks = [];

    /** @var array<string, string> */
    private array $existingIntroImagePaths = [];

    /** @param array<string, string> $imagePaths */
    public function setExistingIntroImagePaths(array $imagePaths): void
    {
        $this->existingIntroImagePaths = $imagePaths;
    }

    #[Assert\Callback]
    public function validateIntroBlocks(ExecutionContextInterface $context): void
    {
        if ([] === $this->introBlocks) {
            $context->buildViolation('mailing.form.intro_text_required')->atPath('introBlocks')->addViolation();

            return;
        }

        $hasText = false;
        $seenIds = [];

        foreach ($this->introBlocks as $index => $block) {
            if (!$block instanceof MailingIntroBlockFormModel) {
                $context->buildViolation('mailing.form.intro_block_invalid')->atPath("introBlocks[{$index}]")->addViolation();
                continue;
            }

            if (1 !== preg_match('/^[a-zA-Z0-9-]{1,64}$/', $block->id) || isset($seenIds[$block->id])) {
                $context->buildViolation('mailing.form.intro_block_invalid')->atPath("introBlocks[{$index}].id")->addViolation();
            }
            $seenIds[$block->id] = true;

            if (!in_array($block->kind, ['paragraph', 'image', 'split'], true)) {
                $context->buildViolation('mailing.form.intro_block_invalid')->atPath("introBlocks[{$index}].kind")->addViolation();
                continue;
            }

            if (in_array($block->kind, ['paragraph', 'split'], true)) {
                if ('' === trim(strip_tags($block->text))) {
                    $context->buildViolation('mailing.form.intro_text_required')->atPath("introBlocks[{$index}].text")->addViolation();
                } else {
                    $hasText = true;
                }
            }

            if (in_array($block->kind, ['image', 'split'], true)) {
                $block->imagePath = $this->existingIntroImagePaths[$block->id] ?? null;
                if (null === $block->imageFile && null === $block->imagePath) {
                    $context->buildViolation('mailing.form.intro_image_required')->atPath("introBlocks[{$index}].imageFile")->addViolation();
                }
                if (!$block->imageDecorative && '' === trim($block->imageAlt ?? '')) {
                    $context->buildViolation('mailing.form.intro_alt_required')->atPath("introBlocks[{$index}].imageAlt")->addViolation();
                }
            }

            if ('split' === $block->kind && !in_array($block->imageSide, ['left', 'right'], true)) {
                $context->buildViolation('mailing.form.intro_block_invalid')->atPath("introBlocks[{$index}].imageSide")->addViolation();
            }
        }

        if (!$hasText) {
            $context->buildViolation('mailing.form.intro_text_required')->atPath('introBlocks')->addViolation();
        }
    }
}
