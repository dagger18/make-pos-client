<?php
namespace App\Command;

use App\Module\Carrier\Repository\ContainerDdTrackingRepository;
use App\Module\Finance\Service\DdCalculatorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:dd:run-accrual',
    description: 'Recompute D&D accrual for all non-final container records whose free period has ended',
)]
class RunDdAccrualCommand extends Command
{
    public function __construct(
        private readonly ContainerDdTrackingRepository $repository,
        private readonly DdCalculatorService           $calculator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $io->title('D&D Accrual Run');
        $today   = new \DateTime('today');
        $records = $this->repository->findAccruing();
        $updated = 0;

        foreach ($records as $record) {
            $before = $record->getAccruedAmount();
            $this->calculator->updateAccrual($record, $today);
            $this->repository->save($record, false);

            $io->writeln(sprintf(
                '  [%d] SHP-%s / %s: %s → %s %s',
                $record->getId(),
                $record->getShipment()?->getId() ?? '?',
                $record->getContainerNumber(),
                $before,
                $record->getAccruedAmount(),
                $record->getCurrency(),
            ));
            $updated++;
        }

        // Flush all at once for performance
        $this->repository->getEntityManager()->flush();

        $io->success("Updated accrual for {$updated} container record(s).");
        return Command::SUCCESS;
    }
}
