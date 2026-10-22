<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Directory;

use App\Infrastructure\Directory\DoctrineDirectoryMunicipalityLookup;
use App\Infrastructure\Doctrine\Entity\DepartmentEntity;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Entity\RegionEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineDirectoryMunicipalityLookupTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testFallsBackToUniqueNameWhenPostalCodeIsAbsentFromMunicipalityReference(): void
    {
        self::bootKernel(['environment' => 'test']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $region = (new RegionEntity())->setCode('98')->setName('Région test');
            $department = (new DepartmentEntity())->setCode('98')->setName('Département test')->setRegion($region);
            $municipality = (new MunicipalityEntity())->setName('Commune test postale')->setPostalCode('38000')->setDepartment($department);
            $entityManager->persist($region);
            $entityManager->persist($department);
            $entityManager->persist($municipality);
            $entityManager->flush();

            $lookup = self::getContainer()->get(DoctrineDirectoryMunicipalityLookup::class);
            self::assertSame($municipality->getId(), $lookup->findIdByNameAndPostalCode('Commune test postale', '38100'));

            $secondMunicipality = (new MunicipalityEntity())->setName('Commune test postale')->setPostalCode('38200')->setDepartment($department);
            $entityManager->persist($secondMunicipality);
            $entityManager->flush();
            self::assertNull($lookup->findIdByNameAndPostalCode('Commune test postale', '38100'));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }
}
