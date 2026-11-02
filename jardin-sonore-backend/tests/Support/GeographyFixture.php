<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Infrastructure\Doctrine\Entity\DepartmentEntity;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Entity\RegionEntity;
use Doctrine\ORM\EntityManagerInterface;

final class GeographyFixture
{
    public static function municipality(EntityManagerInterface $entityManager, string $name, string $postalCode): MunicipalityEntity
    {
        $department = $entityManager->getRepository(DepartmentEntity::class)->findOneBy([]);
        if (!$department instanceof DepartmentEntity) {
            $region = $entityManager->getRepository(RegionEntity::class)->findOneBy(['code' => 'ZZZ']);
            if (!$region instanceof RegionEntity) {
                $region = (new RegionEntity())->setName('Région de test')->setCode('ZZZ');
                $entityManager->persist($region);
            }
            $department = (new DepartmentEntity())->setName('Département de test')->setCode('ZZZ')->setRegion($region);
            $entityManager->persist($department);
            $entityManager->flush();
        }

        $municipality = (new MunicipalityEntity())->setName($name)->setPostalCode($postalCode)->setDepartment($department);
        $entityManager->persist($municipality);

        return $municipality;
    }
}
