<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\RepertoireBlockKind;

final readonly class RepertoireBlockTextParser
{
    /**
     * @return list<SaveRepertoireBlockInput>
     */
    public function parse(string $importText): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $importText) ?: [];
        $contentBlocks = [];

        foreach ($lines as $line) {
            if ('' === trim($line)) {
                $contentBlocks[] = new SaveRepertoireBlockInput(
                    kind: RepertoireBlockKind::BREAK,
                    text: null,
                    gesture: null,
                );

                continue;
            }

            $contentBlocks[] = new SaveRepertoireBlockInput(
                kind: RepertoireBlockKind::LINE,
                text: trim($line),
                gesture: null,
            );
        }

        while ([] !== $contentBlocks && RepertoireBlockKind::BREAK === $contentBlocks[array_key_last($contentBlocks)]->kind) {
            array_pop($contentBlocks);
        }

        return $contentBlocks;
    }
}
