<?php

declare(strict_types=1);

namespace App\Application\Mailing;

interface InvalidRecipientBatchProcessorInterface
{
    /**
     * @param list<string> $emails
     *
     * @return list<InvalidRecipientProcessResult>
     */
    public function process(array $emails, string $action): array;
}
