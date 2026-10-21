<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Commercial\PrepareCommercialInvoices;
use App\Application\Commercial\RefreshCommercialAppointments;
use App\Application\Commercial\RefreshCommercialInterventions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:commercial:refresh-calendar', description: 'Refresh linked commercial interventions and appointments from Google Calendar.')]
final class RefreshCommercialInterventionsCommand extends Command
{
    public function __construct(
        private readonly RefreshCommercialInterventions $refresher,
        private readonly RefreshCommercialAppointments $appointmentRefresher,
        private readonly PrepareCommercialInvoices $invoicePreparer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $refreshedCount = $this->refresher->run();
        $appointmentCount = $this->appointmentRefresher->run();
        $preparationCount = $this->invoicePreparer->run();
        $output->writeln(sprintf('Refreshed %d intervention(s) and %d appointment(s); created %d invoice preparation action(s).', $refreshedCount, $appointmentCount, $preparationCount));

        return Command::SUCCESS;
    }
}
