<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Session\SessionNotificationMailSenderInterface;
use App\Infrastructure\Session\SessionNotificationDeliveryStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:sessions:dispatch-notifications', description: 'Queue pending first-publication notifications for the existing async worker.')]
final class DispatchSessionNotificationsCommand extends Command
{
    public function __construct(
        private readonly SessionNotificationDeliveryStore $sessionNotificationDeliveryStore,
        private readonly MessageBusInterface $messageBus,
        private readonly ?SessionNotificationMailSenderInterface $sessionNotificationMailSender = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('recover-after', null, InputOption::VALUE_REQUIRED, 'Recover interrupted queueing after this many minutes; choose more than the worker cron interval.', '15');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count eligible deliveries without queueing or changing them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $recoverAfter = filter_var($input->getOption('recover-after'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 525600]]);
        if (false === $recoverAfter) {
            $output->writeln('<error>--recover-after must be a positive number of minutes.</error>');

            return Command::INVALID;
        }

        if ($input->getOption('dry-run')) {
            $count = count($this->sessionNotificationDeliveryStore->queueableIds($recoverAfter));
            $output->writeln("Dry run: {$count} notification(s) eligible in this batch (maximum 100).");

            return Command::SUCCESS;
        }

        if (null === $this->sessionNotificationMailSender) {
            $output->writeln('<error>Session notification mail sender is not configured yet. Queueing is disabled until the email implementation is installed.</error>');

            return Command::FAILURE;
        }

        $count = $this->sessionNotificationDeliveryStore->dispatchPending($this->messageBus, $recoverAfter);
        $output->writeln("Queued {$count} session notification(s).");

        return Command::SUCCESS;
    }
}
