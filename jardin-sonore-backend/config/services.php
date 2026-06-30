<?php

declare(strict_types=1);

use App\Application\Mailing\NewsletterAudienceOptionsProviderInterface;
use App\Application\Mailing\NewsletterAudienceResolverInterface;
use App\Application\Storage\RecommendationImageStorageInterface;
use App\Domain\Repository\AdminUserRepositoryInterface;
use App\Domain\Repository\DepartmentRepositoryInterface;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Domain\Repository\MunicipalityRepositoryInterface;
use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use App\Domain\Repository\RegionRepositoryInterface;
use App\Infrastructure\Doctrine\Repository\AdminUserDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\DepartmentDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\MailingCampaignDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\MunicipalityDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\NewsletterRecommendationDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\RegionDoctrineRepository;
use App\Infrastructure\Mailing\DoctrineNewsletterAudienceOptionsProvider;
use App\Infrastructure\Mailing\DoctrineNewsletterAudienceResolver;
use App\Infrastructure\Storage\LocalRecommendationImageStorage;
use Gedmo\Sluggable\SluggableListener;
use Gedmo\Timestampable\TimestampableListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('app.mailing.home_latitude', '')
        ->set('app.mailing.home_longitude', '');

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
    $services->alias(MailingCampaignRepositoryInterface::class, MailingCampaignDoctrineRepository::class);
    $services->alias(NewsletterRecommendationRepositoryInterface::class, NewsletterRecommendationDoctrineRepository::class);
    $services->alias(RecommendationImageStorageInterface::class, LocalRecommendationImageStorage::class);
    $services->alias(NewsletterAudienceResolverInterface::class, DoctrineNewsletterAudienceResolver::class);
    $services->alias(NewsletterAudienceOptionsProviderInterface::class, DoctrineNewsletterAudienceOptionsProvider::class);

    $services->set(TimestampableListener::class)
        ->tag('doctrine.event_subscriber', [
            'connection' => 'default',
        ]);

    $services->set(SluggableListener::class)
        ->tag('doctrine.event_subscriber', [
            'connection' => 'default',
        ]);
};
