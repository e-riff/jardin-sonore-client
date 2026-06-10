<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$objectManager = $kernel->getContainer()->get('doctrine')->getManager();

if (!$objectManager instanceof ObjectManager) {
    throw new RuntimeException('Doctrine manager must implement '.ObjectManager::class.'.');
}

return $objectManager;
