<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Commercial\DispatchCommercialDigest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:commercial:dispatch-digest', description: 'Send the personal commercial digest once on eligible weekdays.')]
final class DispatchCommercialDigestCommand extends Command
{
    public function __construct(private readonly DispatchCommercialDigest $dispatcher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->dispatcher->run() ? 'Commercial digest sent.' : 'No commercial digest to send.');

        return Command::SUCCESS;
    }
}
