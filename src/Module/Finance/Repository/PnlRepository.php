<?php
namespace App\Module\Finance\Repository;

use Doctrine\DBAL\Connection;

class PnlRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function getPeriodPnl(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT
                COALESCE(b.name, 'No Branch')                                                           AS branch,
                COUNT(DISTINCT s.id)                                                                     AS jobs_count,
                SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END)           AS revenue_base,
                SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END)           AS cost_base,
                SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END)
                - SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END)         AS gross_profit,
                COALESCE(SUM(CASE WHEN en.type='RPT' THEN en.fx_gain_loss ELSE 0 END), 0)
                + COALESCE(SUM(CASE WHEN en.type='PMT' THEN en.fx_gain_loss ELSE 0 END), 0)            AS fx_gain_loss,
                SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END)
                - SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END)
                + COALESCE(SUM(CASE WHEN en.type='RPT' THEN en.fx_gain_loss ELSE 0 END), 0)
                + COALESCE(SUM(CASE WHEN en.type='PMT' THEN en.fx_gain_loss ELSE 0 END), 0)            AS net_profit
            FROM ebit_note en
            JOIN shipment s  ON s.id = en.shipment_id
            LEFT JOIN branch b ON b.id = s.branch_id
            WHERE en.type IN ('ID', 'IC', 'RPT', 'PMT')
              AND s.status = 'CO'
              AND DATE(s.completed_date) BETWEEN :from AND :to
            GROUP BY s.branch_id, b.name
            ORDER BY net_profit DESC
        ";
        return $this->connection->fetchAllAssociative($sql, ['from' => $dateFrom, 'to' => $dateTo]);
    }

    public function getDepartmentPnl(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT
                d.id                                                                              AS department_id,
                d.name                                                                            AS department_name,
                COALESCE(b.name, 'No Branch')                                                    AS branch_name,
                COALESCE(d.direction, '')                                                         AS direction,
                COUNT(DISTINCT en.shipment_id)                                                    AS jobs_count,
                SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)    AS revenue_base,
                SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)    AS cost_base,
                SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)
                - SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)  AS gross_profit,
                ROUND(
                    (SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)
                     - SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END))
                    / NULLIF(SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END), 0) * 100,
                    2
                )                                                                                 AS margin_pct
            FROM charge_item ci
            JOIN ebit_note en  ON ci.ebit_note_id = en.id
            JOIN shipment s    ON en.shipment_id = s.id
            JOIN department d  ON ci.department_id = d.id
            LEFT JOIN branch b ON d.branch_id = b.id
            WHERE en.type IN ('ID', 'IC')
              AND s.status = 'CO'
              AND DATE(s.completed_date) BETWEEN :from AND :to
              AND ci.department_id IS NOT NULL
            GROUP BY d.id, d.name, b.name, d.direction
            ORDER BY gross_profit DESC
        ";
        return $this->connection->fetchAllAssociative($sql, ['from' => $dateFrom, 'to' => $dateTo]);
    }

    /**
     * @param int        $shipmentId
     * @param array|null $visibleToFilter Visible-to filter: null = no filter (all rows);
     *                                    [] = only rows where visible_to IS NULL or 'ALL';
     *                                    ['ORIGIN'] = rows where visible_to IS NULL, 'ALL', or 'ORIGIN'.
     *                                    Non-empty lists always include NULL and ALL rows in addition to the
     *                                    listed visible_to codes (valid values: ORIGIN, DESTINATION, ALL).
     * @return array{lines: list<array>, totalSell: float, totalBuy: float, grossProfit: float, marginPct: float}
     */
    public function getJobCostSheet(int $shipmentId, ?array $visibleToFilter = null): array
    {
        $params = ['shipmentId' => $shipmentId];
        $filterClause = '';

        if ($visibleToFilter !== null) {
            if (empty($visibleToFilter)) {
                $filterClause = "AND (ci.visible_to IS NULL OR ci.visible_to = 'ALL')";
            } else {
                $parts = [];
                foreach (array_values(array_unique($visibleToFilter)) as $i => $val) {
                    $key = "dir_{$i}";
                    $params[$key] = $val;
                    $parts[] = ":{$key}";
                }
                $inList = implode(', ', $parts);
                $filterClause = "AND (ci.visible_to IS NULL OR ci.visible_to = 'ALL' OR ci.visible_to IN ({$inList}))";
            }
        }

        $sql = "
            SELECT
                ci.charge_type_name                                                              AS chargeType,
                ci.charge_name                                                                   AS chargeName,
                COALESCE(ci.payable_at, '')                                                      AS payableAt,
                COALESCE(d.name, '')                                                             AS departmentName,
                SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)   AS sellBase,
                SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)   AS buyBase,
                SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)
                - SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END) AS marginBase
            FROM charge_item ci
            JOIN ebit_note en ON ci.ebit_note_id = en.id
            LEFT JOIN department d ON ci.department_id = d.id
            WHERE en.shipment_id = :shipmentId
              AND en.type IN ('ID', 'IC')
              {$filterClause}
            GROUP BY ci.charge_type_name, ci.charge_name, ci.payable_at, d.name
            ORDER BY ABS(marginBase) DESC
        ";
        $lines = $this->connection->fetchAllAssociative($sql, $params);

        $totalSell = array_sum(array_column($lines, 'sellBase'));
        $totalBuy  = array_sum(array_column($lines, 'buyBase'));
        $margin    = $totalSell - $totalBuy;
        $pct       = $totalSell > 0 ? round($margin / $totalSell * 100, 2) : 0;

        return [
            'lines'       => $lines,
            'totalSell'   => round($totalSell, 4),
            'totalBuy'    => round($totalBuy, 4),
            'grossProfit' => round($margin, 4),
            'marginPct'   => $pct,
        ];
    }
}
