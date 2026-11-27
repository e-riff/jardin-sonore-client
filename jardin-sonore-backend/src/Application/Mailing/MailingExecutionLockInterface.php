<?php

declare(strict_types=1);

namespace App\Application\Mailing;

interface MailingExecutionLockInterface
{
    /** @template T
     * @param callable(): T $operation
     *
     * @return T
     */
    public function run(string $key, callable $operation): mixed;
}
