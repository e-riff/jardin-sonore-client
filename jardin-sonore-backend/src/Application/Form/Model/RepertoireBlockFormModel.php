<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Application\Session\RepertoireBlockView;
use App\Domain\Model\Session\RepertoireBlockKind;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class RepertoireBlockFormModel
{
    public RepertoireBlockKind $kind = RepertoireBlockKind::LINE;

    public ?string $text = null;

    public ?string $gesture = null;

    public static function fromView(RepertoireBlockView $repertoireBlockView): self
    {
        $formModel = new self();
        $formModel->kind = RepertoireBlockKind::SECTION === $repertoireBlockView->kind
            ? RepertoireBlockKind::BREAK
            : $repertoireBlockView->kind;
        $formModel->text = $repertoireBlockView->text;
        $formModel->gesture = $repertoireBlockView->gesture;

        return $formModel;
    }

    public static function createLine(?string $text = null): self
    {
        $formModel = new self();
        $formModel->kind = RepertoireBlockKind::LINE;
        $formModel->text = $text;

        return $formModel;
    }

    public static function createBreak(): self
    {
        $formModel = new self();
        $formModel->kind = RepertoireBlockKind::BREAK;

        return $formModel;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $executionContext): void
    {
        if (RepertoireBlockKind::LINE === $this->kind && '' === trim((string) $this->text)) {
            $executionContext->buildViolation('La ligne de paroles ne peut pas être vide.')
                ->atPath('text')
                ->addViolation();
        }
    }
}
