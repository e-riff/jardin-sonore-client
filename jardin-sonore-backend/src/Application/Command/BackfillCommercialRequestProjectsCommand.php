<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Infrastructure\Commercial\BackfillCommercialRequestProjects;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:commercial:backfill-request-projects', description: 'Create one dossier for each historical commercial request without one.')]
final class BackfillCommercialRequestProjectsCommand extends Command
{
    public function __construct(private readonly BackfillCommercialRequestProjects $backfill)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->backfill->run();
        $output->writeln("{$count} dossier(s) créé(s).");

        return Command::SUCCESS;
    }
}
