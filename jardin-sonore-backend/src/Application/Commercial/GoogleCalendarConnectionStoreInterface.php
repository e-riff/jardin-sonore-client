<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\GoogleCalendarConnectionEntity;

interface GoogleCalendarConnectionStoreInterface
{
    public function get(): ?GoogleCalendarConnectionEntity;

    public function save(GoogleCalendarConnectionEntity $connectionEntity): void;

    public function delete(): void;
}
