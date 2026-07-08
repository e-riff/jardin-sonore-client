<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\EventSubscriber\SharedContactSubscriber;
use Gedmo\Sluggable\SluggableListener;
use Gedmo\Timestampable\TimestampableListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import('parameters.yaml.dist', 'yaml');
    $containerConfigurator->import('parameters.yaml', 'yaml');

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\', '../src/')
        ->exclude([
            '../src/Infrastructure/Doctrine/Mapping',
            '../src/Kernel.php',
        ]);

    $services->set(TimestampableListener::class)
        ->tag('doctrine.event_subscriber', [
            'connection' => 'default',
        ]);

    $services->set(SluggableListener::class)
        ->tag('doctrine.event_subscriber', [
            'connection' => 'default',
        ]);

    $services->set(SharedContactSubscriber::class)
        ->tag('doctrine.event_subscriber', [
            'connection' => 'default',
        ]);
};
