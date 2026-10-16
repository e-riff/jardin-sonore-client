<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Commercial\PrepareCommercialInvoices;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:commercial:prepare-invoices', description: 'Create one invoice preparation action after each dossier’s final confirmed session of the service month.')]
final class PrepareCommercialInvoicesCommand extends Command
{
    public function __construct(private readonly PrepareCommercialInvoices $preparer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('Created %d invoice preparation action(s).', $this->preparer->run()));

        return Command::SUCCESS;
    }
}
