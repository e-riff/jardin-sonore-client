<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use InvalidArgumentException;

class CommercialDigestSettingsEntity
{
    public const int SINGLETON_ID = 1;

    private int $id = self::SINGLETON_ID;

    private bool $enabled = true;

    private string $sendTime = '08:00';

    public function getId(): int
    {
        return $this->id;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getSendTime(): string
    {
        return $this->sendTime;
    }

    public function setSendTime(string $sendTime): void
    {
        if (1 !== preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $sendTime)) {
            throw new InvalidArgumentException('Digest time must use HH:mm.');
        }

        $this->sendTime = $sendTime;
    }
}
