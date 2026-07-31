<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Application\Session\SessionSequenceLocalSetting;
use App\Application\Session\UpdateSessionSequenceLocalSetting;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'SessionSequenceLocalDetails', template: 'components/SessionSequenceLocalDetails.html.twig', method: 'get')]
final class SessionSequenceLocalDetails
{
    use DefaultActionTrait;

    #[LiveProp] public string $sessionUuid;
    #[LiveProp] public string $sequenceUuid;
    #[LiveProp] public string $section = 'details';
    #[LiveProp] public bool $showInstructionLabel = false;
    #[LiveProp(writable: true)] public ?string $role = null;
    #[LiveProp(writable: true)] public string $body = '';
    #[LiveProp(writable: true)] public ?string $notes = null;
    #[LiveProp] public string $editing = '';
    #[LiveProp(writable: true)] public string $instructionValue = '';
    #[LiveProp] public int $editingInstructionIndex = -1;
    public function __construct(private readonly UpdateSessionSequenceLocalSetting $updateSessionSequenceLocalSetting)
    {
    }

    #[LiveAction]
    public function edit(#[LiveArg] string $field): void
    {
        if (in_array($field, ['role', 'notes'], true)) {
            $this->editing = $field;
        }
    }

    #[LiveAction]
    public function startEditInstruction(#[LiveArg] int $index): void
    {
        $instructions = $this->getInstructions();

        if (!isset($instructions[$index])) {
            return;
        }

        $this->instructionValue = $instructions[$index];
        $this->editingInstructionIndex = $index;
    }

    #[LiveAction]
    public function addInstruction(): void
    {
        $this->instructionValue = '';
        $this->editingInstructionIndex = count($this->getInstructions());
    }

    #[LiveAction]
    public function moveInstruction(#[LiveArg] int $index, #[LiveArg] int $direction): void
    {
        $instructions = $this->getInstructions();
        $destinationIndex = $index + $direction;

        if (!in_array($direction, [-1, 1], true) || !isset($instructions[$index], $instructions[$destinationIndex])) {
            return;
        }

        [$instructions[$index], $instructions[$destinationIndex]] = [$instructions[$destinationIndex], $instructions[$index]];
        $this->body = implode("\n", array_values($instructions));
        $this->saveSetting(SessionSequenceLocalSetting::BODY, $this->body);
    }

    #[LiveAction]
    public function save(): void
    {
        $setting = SessionSequenceLocalSetting::tryFrom($this->editing);
        if (null === $setting || !Uuid::isValid($this->sessionUuid) || !Uuid::isValid($this->sequenceUuid)) {
            return;
        }
        $value = match ($setting) {
            SessionSequenceLocalSetting::ROLE => $this->role ?? '',
            SessionSequenceLocalSetting::BODY => $this->body,
            SessionSequenceLocalSetting::NOTES => $this->notes ?? '',
        };
        $this->saveSetting($setting, $value);
        $this->editing = '';
    }

    #[LiveAction]
    public function saveInstruction(): void
    {
        $instructions = $this->getInstructions();
        $instruction = trim($this->instructionValue);

        if ($this->editingInstructionIndex < count($instructions)) {
            if ('' === $instruction) {
                unset($instructions[$this->editingInstructionIndex]);
            } else {
                $instructions[$this->editingInstructionIndex] = $instruction;
            }
        } elseif ('' !== $instruction) {
            $instructions[] = $instruction;
        }

        $this->body = implode("\n", array_values($instructions));
        $this->saveSetting(SessionSequenceLocalSetting::BODY, $this->body);
        $this->instructionValue = '';
        $this->editingInstructionIndex = -1;
    }

    #[LiveAction]
    public function saveAndAddInstruction(): void
    {
        $this->saveInstruction();
        $this->addInstruction();
    }

    /** @return list<string> */
    public function getInstructions(): array
    {
        return array_values(array_filter(array_map(trim(...), explode("\n", $this->body))));
    }

    private function saveSetting(SessionSequenceLocalSetting $setting, string $value): void
    {
        if (!Uuid::isValid($this->sessionUuid) || !Uuid::isValid($this->sequenceUuid)) {
            return;
        }

        ($this->updateSessionSequenceLocalSetting)(Uuid::fromString($this->sessionUuid), Uuid::fromString($this->sequenceUuid), $setting, $value);
    }
}
