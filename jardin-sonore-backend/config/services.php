<?php

declare(strict_types=1);

use App\Application\Mailing\NewsletterAudienceOptionsProviderInterface;
use App\Application\Mailing\NewsletterAudienceResolverInterface;
use App\Application\Mailing\NewsletterMailSenderInterface;
use App\Application\Mailing\NewsletterRendererInterface;
use App\Application\Storage\MailingBannerImageStorageInterface;
use App\Application\Storage\RecommendationImageStorageInterface;
use App\Domain\Repository\AdminUserRepositoryInterface;
use App\Domain\Repository\DepartmentRepositoryInterface;
use App\Domain\Repository\EmailContactRepositoryInterface;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Domain\Repository\MunicipalityRepositoryInterface;
use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use App\Domain\Repository\OrganizationRepositoryInterface;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\PhoneContactRepositoryInterface;
use App\Domain\Repository\RegionRepositoryInterface;
use App\Infrastructure\Doctrine\Repository\AdminUserDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\DepartmentDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\MailingCampaignDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\MunicipalityDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\NewsletterRecommendationDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\OrganizationDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\PersonDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\PhoneContactDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\RegionDoctrineRepository;
use App\Infrastructure\Mailing\DoctrineNewsletterAudienceOptionsProvider;
use App\Infrastructure\Mailing\DoctrineNewsletterAudienceResolver;
use App\Infrastructure\Mailing\TwigNewsletterRenderer;
use App\Infrastructure\Mailer\SymfonyNewsletterMailSender;
use App\Infrastructure\Storage\LocalMailingBannerImageStorage;
use App\Infrastructure\Storage\LocalRecommendationImageStorage;
use Gedmo\Sluggable\SluggableListener;
use Gedmo\Timestampable\TimestampableListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('app.mailing.home_latitude', '')
        ->set('app.mailing.home_longitude', '')
        ->set('app.mailing.hourly_limit', 45)
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

    $services->alias(RegionRepositoryInterface::class, RegionDoctrineRepository::class);
    $services->alias(DepartmentRepositoryInterface::class, DepartmentDoctrineRepository::class);
    $services->alias(MunicipalityRepositoryInterface::class, MunicipalityDoctrineRepository::class);
    $services->alias(AdminUserRepositoryInterface::class, AdminUserDoctrineRepository::class);
    $services->alias(EmailContactRepositoryInterface::class, EmailContactDoctrineRepository::class);
    $services->alias(MailingCampaignRepositoryInterface::class, MailingCampaignDoctrineRepository::class);
    $services->alias(NewsletterRecommendationRepositoryInterface::class, NewsletterRecommendationDoctrineRepository::class);
    $services->alias(OrganizationRepositoryInterface::class, OrganizationDoctrineRepository::class);
    $services->alias(PersonRepositoryInterface::class, PersonDoctrineRepository::class);
    $services->alias(PhoneContactRepositoryInterface::class, PhoneContactDoctrineRepository::class);
    $services->alias(MailingBannerImageStorageInterface::class, LocalMailingBannerImageStorage::class);
    $services->alias(RecommendationImageStorageInterface::class, LocalRecommendationImageStorage::class);
    $services->alias(NewsletterAudienceResolverInterface::class, DoctrineNewsletterAudienceResolver::class);
    $services->alias(NewsletterAudienceOptionsProviderInterface::class, DoctrineNewsletterAudienceOptionsProvider::class);
    $services->alias(NewsletterMailSenderInterface::class, SymfonyNewsletterMailSender::class);
    $services->alias(NewsletterRendererInterface::class, TwigNewsletterRenderer::class);

    $services->set(TimestampableListener::class)
        ->tag('doctrine.event_subscriber', [
            'connection' => 'default',
        ]);

    $services->set(SluggableListener::class)
        ->tag('doctrine.event_subscriber', [
            'connection' => 'default',
        ]);
};
