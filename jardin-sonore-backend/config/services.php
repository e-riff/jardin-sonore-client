<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\EventSubscriber\SharedContactSubscriber;
use Gedmo\Sluggable\SluggableListener;
use Gedmo\Timestampable\TimestampableListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('app.mailing.home_latitude_default', '')
        ->set('app.mailing.home_longitude_default', '')
        ->set('app.mailing.window_limit_default', 45)
        ->set('app.mailing.window_minutes_default', 60)
        ->set('app.mailing.dispatch_batch_size_default', 15)
        ->set('app.mailing.home_latitude', '%env(default:app.mailing.home_latitude_default:MAILING_HOME_LATITUDE)%')
        ->set('app.mailing.home_longitude', '%env(default:app.mailing.home_longitude_default:MAILING_HOME_LONGITUDE)%')
        ->set('app.mailing.window_limit', '%env(int:default:app.mailing.window_limit_default:MAILING_WINDOW_LIMIT)%')
        ->set('app.mailing.window_minutes', '%env(int:default:app.mailing.window_minutes_default:MAILING_WINDOW_MINUTES)%')
        ->set('app.mailing.dispatch_batch_size', '%env(int:default:app.mailing.dispatch_batch_size_default:MAILING_DISPATCH_BATCH_SIZE)%');
    $containerConfigurator->parameters()
        ->set('app.mailing.from_email_default', 'contact@jardinsonore.fr')
        ->set('app.mailing.from_name_default', 'Jardin Sonore')
        ->set('app.mailing.from_email', '%env(default:app.mailing.from_email_default:DEFAULT_CONTACT)%')
        ->set('app.mailing.from_name', '%env(default:app.mailing.from_name_default:MAILING_FROM_NAME)%');

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
