<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\EventSubscriber\SharedContactSubscriber;
use Gedmo\Sluggable\SluggableListener;
use Gedmo\Timestampable\TimestampableListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('app.mailing.home_latitude', '')
        ->set('app.mailing.home_longitude', '')
        ->set('app.mailing.window_limit', 45)
        ->set('app.mailing.window_minutes', 60)
        ->set('app.mailing.dispatch_batch_size', 15);
    $containerConfigurator->parameters()
        ->set('app.mailing.from_email', 'contact@jardinsonore.fr')
        ->set('app.mailing.from_name', 'Jardin Sonore');

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
