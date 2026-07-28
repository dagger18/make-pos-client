<?php
namespace App\Command;

use App\Module\Carrier\Entity\CarrierPerformanceScore;
use App\Module\Carrier\Entity\Provider;
use App\Module\Carrier\Repository\CargoClaimRepository;
use App\Module\Carrier\Repository\CarrierPerformanceScoreRepository;
use App\Module\Carrier\Service\CarrierPerformanceScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:carrier:compute-scores',
    description: 'Compute monthly carrier performance scores from cargo claim data',
)]
class ComputeCarrierScoresCommand extends Command
{
    private const MODES = ['OCN', 'AIR', 'RD'];
    private const MIN_SHIPMENTS = 5;

    public function __construct(
        private readonly CargoClaimRepository             $claimRepo,
        private readonly CarrierPerformanceScoreRepository $scoreRepo,
        private readonly CarrierPerformanceScoreService   $scoreService,
        private readonly EntityManagerInterface           $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('year',  null, InputOption::VALUE_OPTIONAL, 'Period year (default: previous month year)')
             ->addOption('month', null, InputOption::VALUE_OPTIONAL, 'Period month 1-12 (default: previous month)')
             ->addOption('mode',  null, InputOption::VALUE_OPTIONAL, 'Transport mode OCN/AIR/RD (default: all)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Carrier Performance Score Computation');

        $prevMonth = new \DateTime('first day of last month');
        $year  = (int) ($input->getOption('year')  ?? $prevMonth->format('Y'));
        $month = (int) ($input->getOption('month') ?? $prevMonth->format('n'));
        $modes = $input->getOption('mode') ? [$input->getOption('mode')] : self::MODES;

        $io->comment(sprintf('Period: %d-%02d | Modes: %s', $year, $month, implode(', ', $modes)));

        $totalUpdated = 0;

        foreach ($modes as $mode) {
            $io->section("Mode: {$mode}");
            $aggregated = $this->claimRepo->aggregateForPeriod($year, $month, $mode);

            if (empty($aggregated)) {
                $io->writeln('  No claim data found — skipping.');
                continue;
            }

            foreach ($aggregated as $carrierId => $metrics) {
                $shipmentCount = $metrics['shipmentCount'];
                $claimCount    = $metrics['claimCount'];

                if ($shipmentCount < self::MIN_SHIPMENTS) {
                    $io->writeln("  [carrier #{$carrierId}] Skipped — only {$shipmentCount} shipment(s), need " . self::MIN_SHIPMENTS);
                    continue;
                }

                $claimsPer100 = round(($claimCount / $shipmentCount) * 100, 3);

                $composite = $this->scoreService->computeComposite(
                    null, null, null, null, null, $claimsPer100
                );

                $score = $this->scoreRepo->findOneForPeriod($carrierId, $year, $month, $mode)
                      ?? (new CarrierPerformanceScore())
                            ->setCarrier($this->em->getReference(Provider::class, $carrierId))
                            ->setPeriodYear($year)
                            ->setPeriodMonth($month)
                            ->setTransportMode($mode);

                $score->setCargoClaimsCount($claimCount)
                      ->setShipmentsTotal($shipmentCount)
                      ->setClaimsPer100($claimsPer100)
                      ->setCompositeScore($composite)
                      ->setScoreBand($composite !== null ? $this->scoreService->scoreBand($composite) : null)
                      ->setCalculatedAt(new \DateTime());

                $this->scoreRepo->save($score, false);
                $totalUpdated++;

                $io->writeln(sprintf(
                    '  [carrier #%d] claims=%d shipments=%d claims/100=%.1f composite=%s band=%s',
                    $carrierId, $claimCount, $shipmentCount, $claimsPer100,
                    $composite !== null ? number_format($composite, 2) : 'N/A',
                    $score->getScoreBand() ?? 'N/A'
                ));
            }
        }

        $this->em->flush();
        $io->success("Computed/updated {$totalUpdated} score record(s).");
        return Command::SUCCESS;
    }
}
