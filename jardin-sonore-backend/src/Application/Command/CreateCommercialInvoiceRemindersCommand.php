<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Commercial\CreateOverdueInvoiceActions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:commercial:create-invoice-reminders', description: 'Create one internal overdue reminder for each unpaid commercial invoice.')]
final class CreateCommercialInvoiceRemindersCommand extends Command
{
    public function __construct(private readonly CreateOverdueInvoiceActions $creator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->creator->run();
        $output->writeln("Created {$count} invoice reminder action(s).");

        return Command::SUCCESS;
    }
}
