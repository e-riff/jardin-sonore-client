<?php

declare(strict_types=1);

namespace App\Application\Geography;

interface AddressMunicipalityLinkingReaderInterface
{
    /**
     * @return iterable<int, AddressContactSnapshot>
     */
    public function iterateUnlinkedAddressSnapshots(): iterable;

    /**
     * @return list<AddressMunicipalityCandidate>
     */
    public function findMunicipalityCandidatesByPostalCode(string $postalCode): array;

    /**
     * @return list<AddressMunicipalityCandidate>
     */
    public function findMunicipalityCandidatesByDepartmentCode(string $departmentCode): array;
}
