<?php

declare(strict_types=1);

namespace App\Application\Geography;

interface AddressMunicipalityLinkingWriterInterface
{
    public function linkAddressContactToMunicipality(int $addressContactId, int $municipalityId): bool;

    public function flush(): void;

    public function clear(): void;
}
