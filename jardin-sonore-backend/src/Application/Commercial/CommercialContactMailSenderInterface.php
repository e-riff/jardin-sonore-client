<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;

interface CommercialContactMailSenderInterface
{
    public function send(CommercialRequestEntity $requestEntity): void;
}
