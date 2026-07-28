<?php
declare(strict_types=1);
namespace App\Module\Quote\Controller;

use App\Module\Core\Service\MasterSyncService;
use App\Module\Quote\Repository\RateBenchmarkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rate-benchmark')]
#[IsGranted('ROLE_USER')]
#[AppModule('quote')]
class RateBenchmarkingController extends AbstractController
{
    public function __construct(
        private readonly RateBenchmarkRepository $repo,
        private readonly MasterSyncService $masterSyncService,
    ) {}

    /**
     * GET /rate-benchmark/buy?mode=OCN&container_type=40GP
     * Returns active buy rates enriched with market rates from master API.
     * buy_premium_pct < 0 means buying below market (good). > 0 means above market (bad).
     */
    #[Route('/buy', methods: ['GET'])]
    public function buyComparison(Request $request): JsonResponse
    {
        $mode          = $request->query->get('mode')           ?: null;
        $containerType = $request->query->get('container_type') ?: null;

        $rows = $this->repo->getActiveBuyRates($mode, $containerType);

        // Collect unique lane combos for batch market rate lookup
        $seen  = [];
        $lanes = [];
        foreach ($rows as $row) {
            $key = "{$row['pol_code']}|{$row['pod_code']}|{$row['transport_type']}|{$row['container_type']}";
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $lanes[] = [
                    'pol'            => $row['pol_code'],
                    'pod'            => $row['pod_code'],
                    'mode'           => $row['transport_type'],
                    'container_type' => $row['container_type'],
                ];
            }
        }

        // Master API returns map: "pol|pod|mode|container_type" → { rate_amount, index_source, rate_date, currency }
        $marketRates = $this->masterSyncService->getMarketRates($lanes);

        foreach ($rows as &$row) {
            $key    = "{$row['pol_code']}|{$row['pod_code']}|{$row['transport_type']}|{$row['container_type']}";
            $market = $marketRates[$key] ?? null;

            $buyRate    = $row['buy_rate'] !== null ? (float) $row['buy_rate'] : null;
            $marketRate = $market ? (float) $market['rate_amount'] : null;

            $row['market_rate']     = $marketRate;
            $row['market_index']    = $market['index_source'] ?? null;
            $row['market_date']     = $market['rate_date']    ?? null;
            $row['buy_vs_market']   = ($buyRate !== null && $marketRate !== null)
                ? round($buyRate - $marketRate, 2) : null;
            $row['buy_premium_pct'] = ($buyRate !== null && $marketRate !== null && $marketRate != 0)
                ? round(($buyRate - $marketRate) / $marketRate * 100, 1) : null;
        }
        unset($row);

        return $this->json($rows);
    }

    /**
     * GET /rate-benchmark/sell?from=2026-01-01&to=2026-06-30&mode=OCN
     * Returns sell comparison: our avg sell rate vs current market rate per lane.
     * sell_premium_pct > 0 means selling above market (good margin, may lose quotes).
     */
    #[Route('/sell', methods: ['GET'])]
    public function sellComparison(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to',   date('Y-m-d'));
        $mode = $request->query->get('mode') ?: null;

        $rows = $this->repo->getSellComparison($from, $to, $mode);

        // Only OCN lanes are benchmarked against a container index (40GP reference).
        // AIR/RD/RAL rows receive null market_rate and are excluded from the batch call.
        $seenLanes = [];
        $lanes     = [];
        foreach ($rows as $r) {
            if ($r['transport_type'] === 'OCN') {
                $lk = "{$r['pol_code']}|{$r['pod_code']}|OCN|40GP";
                if (!isset($seenLanes[$lk])) {
                    $seenLanes[$lk] = true;
                    $lanes[] = [
                        'pol'            => $r['pol_code'],
                        'pod'            => $r['pod_code'],
                        'mode'           => 'OCN',
                        'container_type' => '40GP',
                    ];
                }
            }
        }

        $marketRates = $this->masterSyncService->getMarketRates($lanes);

        foreach ($rows as &$row) {
            $key       = "{$row['pol_code']}|{$row['pod_code']}|{$row['transport_type']}|40GP";
            $market    = $row['transport_type'] === 'OCN' ? ($marketRates[$key] ?? null) : null;
            $avgSell   = $row['avg_sell'] !== null ? (float) $row['avg_sell'] : null;
            $marketRate = $market ? (float) $market['rate_amount'] : null;

            $row['market_rate']      = $marketRate;
            $row['market_index']     = $market['index_source'] ?? null;
            $row['sell_premium_pct'] = ($avgSell !== null && $marketRate !== null && $marketRate != 0)
                ? round(($avgSell - $marketRate) / $marketRate * 100, 1) : null;
        }
        unset($row);

        return $this->json($rows);
    }

    /**
     * GET /rate-benchmark/trend?pol=SGSIN&pod=NLRTM&container_type=40GP&days=180
     * Pure proxy to master API market rate trend data.
     */
    #[Route('/trend', methods: ['GET'])]
    public function trend(Request $request): JsonResponse
    {
        $pol           = trim($request->query->getString('pol', ''));
        $pod           = trim($request->query->getString('pod', ''));
        $containerType = $request->query->get('container_type') ?: null;
        $days          = max(7, min(365, (int) $request->query->get('days', 180)));

        if (!$pol || !$pod) {
            return $this->json(['error' => $this->trans('pol and pod are required')], 400);
        }

        return $this->json(
            $this->masterSyncService->getMarketRateTrend($pol, $pod, $containerType, $days)
        );
    }
}
