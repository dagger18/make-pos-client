<?php
namespace App\Command;

use App\Module\Crm\Entity\Client;
use App\Module\Finance\Enum\CreditStatus;
use App\Module\Finance\Repository\AgeingRepository;
use App\Module\Crm\Repository\ClientRepository;
use App\Module\Finance\Service\CreditCheckService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:credit-control:update-statuses',
    description: 'Auto-escalate client credit status based on overdue invoices (>30d ON_HOLD, >90d BLOCKED)',
)]
class UpdateClientCreditStatusCommand extends Command
{
    public function __construct(
        private readonly AgeingRepository       $ageingRepository,
        private readonly ClientRepository       $clientRepository,
        private readonly CreditCheckService     $creditCheckService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Credit Control: Auto Status Update');

        $overdueData = $this->ageingRepository->getClientsWithOverdueData();

        // Group by client_id — keep worst (max days overdue) across currencies
        $clientWorst = [];
        foreach ($overdueData as $row) {
            $cid = (int) $row['client_id'];
            if (!isset($clientWorst[$cid]) || $row['max_days_overdue'] > $clientWorst[$cid]['max_days_overdue']) {
                $clientWorst[$cid] = $row;
            }
        }

        $updated = 0;
        foreach ($clientWorst as $clientId => $row) {
            /** @var Client|null $client */
            $client = $this->clientRepository->find($clientId);
            if (!$client) {
                continue;
            }

            $maxDays       = (int) $row['max_days_overdue'];
            $currentStatus = $client->getCreditStatus();

            // Never auto-escalate a manually Blacklisted client
            if ($currentStatus === CreditStatus::Blacklisted) {
                continue;
            }

            $targetStatus = match(true) {
                $maxDays > 90 => CreditStatus::Blocked,
                $maxDays > 30 => CreditStatus::OnHold,
                default       => null,
            };

            if ($targetStatus === null || $targetStatus === $currentStatus) {
                continue;
            }

            $reason = "Auto-escalated: oldest overdue invoice is {$maxDays} days overdue";

            $this->creditCheckService->recordHistory(
                client:         $client,
                changedBy:      null,
                changeType:     'AUTO_ESCALATION',
                oldStatus:      $currentStatus,
                newStatus:      $targetStatus,
                oldLimitAmount: $client->getCreditLimit()?->getAmount(),
                newLimitAmount: $client->getCreditLimit()?->getAmount(),
                currency:       $client->getCreditLimit()?->getCurrency(),
                reason:         $reason,
            );

            $client->setCreditStatus($targetStatus);
            $client->setCreditHoldReason($reason);
            $this->em->flush();

            $io->writeln(sprintf(
                '  [%d] %s: %s → %s (%d days overdue)',
                $clientId,
                $client->getName(),
                $currentStatus->value,
                $targetStatus->value,
                $maxDays
            ));
            $updated++;
        }

        $io->success("Updated {$updated} client(s).");
        return Command::SUCCESS;
    }
}
