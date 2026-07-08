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
                ->select('address.id', 'address.postal_code', 'address.city', 'address.address')
                ->from('address_contact', 'address')
                ->andWhere('address.id > :lastId')
                ->andWhere('address.municipality_id IS NULL')
                ->setParameter('lastId', $lastId)
                ->orderBy('address.id', 'ASC')
                ->setMaxResults(self::READ_BATCH_SIZE);

            /** @var list<array{id:int|string, postal_code:?string, city:?string, address:?string}> $rows */
            $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

            foreach ($rows as $row) {
                $lastId = (int) $row['id'];

                yield new AddressContactSnapshot(
                    id: (int) $row['id'],
                    postalCode: is_string($row['postal_code']) ? trim($row['postal_code']) : null,
                    city: is_string($row['city']) ? trim($row['city']) : null,
                    address: is_string($row['address']) ? trim($row['address']) : null,
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
