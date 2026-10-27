<?php

declare(strict_types=1);

namespace App\Infrastructure\Geography;

use App\Application\Geography\AddressContactSnapshot;
use App\Application\Geography\AddressMunicipalityCandidate;
use App\Application\Geography\AddressMunicipalityLinkingReaderInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DoctrineAddressMunicipalityLinkingReader implements AddressMunicipalityLinkingReaderInterface
{
    private const int READ_BATCH_SIZE = 200;

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function iterateUnlinkedAddressSnapshots(): iterable
    {
        $lastId = 0;

        do {
            $queryBuilder = $this->connection->createQueryBuilder()
                ->select('address.id', 'address.address')
                ->from('address_contact', 'address')
                ->andWhere('address.id > :lastId')
                ->andWhere('address.municipality_id IS NULL')
                ->setParameter('lastId', $lastId)
                ->orderBy('address.id', 'ASC')
                ->setMaxResults(self::READ_BATCH_SIZE);

            /** @var list<array{id:int|string, address:?string}> $rows */
            $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

            foreach ($rows as $row) {
                $lastId = (int) $row['id'];

                $address = is_string($row['address']) ? trim($row['address']) : null;
                $postalCode = null;
                $city = null;
                if (is_string($address) && 1 === preg_match('/\b(\d{5})\s+(.+)$/u', $address, $matches)) {
                    $postalCode = $matches[1];
                    $city = trim($matches[2]);
                }

                yield new AddressContactSnapshot(
                    id: (int) $row['id'],
                    postalCode: $postalCode,
                    city: $city,
                    address: $address,
                );
            }
        } while ([] !== $rows);
    }

    public function findMunicipalityCandidatesByPostalCode(string $postalCode): array
    {
        return $this->mapMunicipalityCandidates(
            $this->connection->fetchAllAssociative(
                'SELECT municipality.id, municipality.name
                FROM municipality
                WHERE municipality.postal_code = :postalCode',
                [
                    'postalCode' => $postalCode,
                ],
                [
                    'postalCode' => ParameterType::STRING,
                ],
            ),
        );
    }

    public function findMunicipalityCandidatesByDepartmentCode(string $departmentCode): array
    {
        return $this->mapMunicipalityCandidates(
            $this->connection->fetchAllAssociative(
                'SELECT municipality.id, municipality.name
                FROM municipality
                INNER JOIN department ON department.id = municipality.department_id
                WHERE department.code = :departmentCode',
                [
                    'departmentCode' => $departmentCode,
                ],
                [
                    'departmentCode' => ParameterType::STRING,
                ],
            ),
        );
    }

    /**
     * @param list<array{id:int|string, name:string}> $rows
     *
     * @return list<AddressMunicipalityCandidate>
     */
    private function mapMunicipalityCandidates(array $rows): array
    {
        $candidates = [];

        foreach ($rows as $row) {
            $name = trim((string) $row['name']);

            if ('' === $name) {
                continue;
            }

            $candidates[] = new AddressMunicipalityCandidate(
                municipalityId: (int) $row['id'],
                name: $name,
            );
        }

        return $candidates;
    }
}
