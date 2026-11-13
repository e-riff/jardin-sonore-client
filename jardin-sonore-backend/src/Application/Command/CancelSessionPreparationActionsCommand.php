<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Commercial\CommercialActionWorkflow;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoicePreparationEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:commercial:cancel-session-preparations', description: 'Preview obsolete session preparation tasks; use --apply to cancel them while preserving their history.')]
final class CancelSessionPreparationActionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommercialActionWorkflow $workflow,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Cancel the listed tasks.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $actionEntities = $this->entityManager->getRepository(CommercialActionEntity::class)->findBy(['status' => CommercialActionEntity::STATUS_OPEN]);
        $count = 0;
        foreach ($actionEntities as $actionEntity) {
            if (!str_starts_with($actionEntity->getTitle(), 'Préparer la séance ')) {
                continue;
            }
            if (null !== $this->entityManager->getRepository(CommercialInvoicePreparationEntity::class)->findOneBy(['action' => $actionEntity])) {
                continue;
            }
            $output->writeln(sprintf('#%d — %s — %s', $actionEntity->getId(), $actionEntity->getProject()->getTitle(), $actionEntity->getTitle()), OutputInterface::OUTPUT_RAW);
            if ($input->getOption('apply')) {
                $this->workflow->cancel($actionEntity);
            }
            ++$count;
        }
        if ($input->getOption('apply')) {
            $this->entityManager->flush();
        }
        $output->writeln(sprintf('%d session preparation task(s). %s', $count, $input->getOption('apply') ? 'Canceled.' : 'Preview only; use --apply to cancel.'));

        return Command::SUCCESS;
    }
}
