<?php

declare(strict_types=1);

use App\Domain\Repository\AdminUserRepositoryInterface;
use App\Domain\Repository\DepartmentRepositoryInterface;
use App\Domain\Repository\MunicipalityRepositoryInterface;
use App\Domain\Repository\RegionRepositoryInterface;
use App\Infrastructure\Doctrine\Repository\AdminUserDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\DepartmentDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\MunicipalityDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\RegionDoctrineRepository;
use Gedmo\Sluggable\SluggableListener;
use Gedmo\Timestampable\TimestampableListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\', '../src/')
        ->exclude([
            '../src/Infrastructure/Doctrine/Mapping',
            '../src/Kernel.php',
        ]);

    $services->alias(RegionRepositoryInterface::class, RegionDoctrineRepository::class);
    $services->alias(DepartmentRepositoryInterface::class, DepartmentDoctrineRepository::class);
    $services->alias(MunicipalityRepositoryInterface::class, MunicipalityDoctrineRepository::class);
    $services->alias(AdminUserRepositoryInterface::class, AdminUserDoctrineRepository::class);

    $services->set(TimestampableListener::class)
        ->tag('doctrine.event_subscriber', [
            'connection' => 'default',
        ]);

    $services->set(SluggableListener::class)
        ->tag('doctrine.event_subscriber', [
            'connection' => 'default',
        ]);
};
