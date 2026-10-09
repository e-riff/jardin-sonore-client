<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Commercial\CommercialContactMailSenderInterface;
use App\Infrastructure\Commercial\ContactRequestDeliveryStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'app:commercial:dispatch-contact-requests', description: 'Send pending contact request emails and retry failed deliveries.')]
final class DispatchCommercialContactRequestsCommand extends Command
{
    public function __construct(private readonly ContactRequestDeliveryStore $deliveryStore, private readonly CommercialContactMailSenderInterface $mailSender)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count pending deliveries without sending mail.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deliveryIds = $this->deliveryStore->queueableIds();
        if ($input->getOption('dry-run')) {
            $output->writeln(sprintf('%d contact email(s) pending in this batch.', count($deliveryIds)));

            return Command::SUCCESS;
        }

        $failures = 0;
        foreach ($deliveryIds as $deliveryId) {
            try {
                $this->deliveryStore->deliver($deliveryId, $this->mailSender->send(...));
            } catch (Throwable $throwable) {
                ++$failures;
                $output->writeln("<error>Contact delivery {$deliveryId} failed: {$throwable->getMessage()}</error>");
            }
        }

        $output->writeln(sprintf('%d contact email(s) processed, %d failed.', count($deliveryIds), $failures));

        return 0 === $failures ? Command::SUCCESS : Command::FAILURE;
    }
}
