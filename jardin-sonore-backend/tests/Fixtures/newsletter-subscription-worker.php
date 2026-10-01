<?php

declare(strict_types=1);

use App\Application\Mailing\NewsletterConfirmationMailSenderInterface;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\NewsletterSubscriptionRequestDoctrineRepository;
use App\Infrastructure\Mailing\FreeNewsletterSubscriptionManager;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;

$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'test';
require dirname(__DIR__) . '/bootstrap.php';

$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$entityManager = $container->get(EntityManagerInterface::class);
if (!str_ends_with((string) $entityManager->getConnection()->getDatabase(), '_test')) {
    throw new RuntimeException('Only the local test database is allowed.');
}
$sender = new class($argv[2]) implements NewsletterConfirmationMailSenderInterface {
    public function __construct(private readonly string $counterPath)
    {
    }

    public function sendConfirmation(string $emailAddress, string $rawToken): void
    {
        $stream = fopen($this->counterPath, 'c+');
        if (false === $stream) {
            throw new RuntimeException('Counter unavailable.');
        }
        flock($stream, LOCK_EX);
        $count = (int) stream_get_contents($stream);
        rewind($stream);
        fwrite($stream, (string) ($count + 1));
        fflush($stream);
        flock($stream, LOCK_UN);
        fclose($stream);
    }
};
$manager = new FreeNewsletterSubscriptionManager($entityManager, $container->get(EmailContactDoctrineRepository::class), $container->get(NewsletterSubscriptionRequestDoctrineRepository::class), new MockClock('2026-10-01 12:00:00 UTC'), $sender);
$deadline = microtime(true) + 10;
while (!is_file($argv[3])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Start barrier unavailable.');
    }
    usleep(10000);
}
$manager->requestSubscription($argv[1]);
$kernel->shutdown();
