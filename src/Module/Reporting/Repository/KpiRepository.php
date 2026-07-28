<?php
namespace App\Module\Reporting\Repository;

use Doctrine\DBAL\Connection;

class KpiRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function getOnTimeRate(string $from, string $to): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN sm.actual_date <= sm.planned_date THEN 1 ELSE 0 END) AS on_time,
                COUNT(*) - SUM(CASE WHEN sm.actual_date <= sm.planned_date THEN 1 ELSE 0 END) AS delayed
            FROM shipment_milestone sm
            WHERE sm.milestone_code IN ('VESSEL_DEPARTED', 'FLIGHT_DEPARTED')
              AND sm.planned_date IS NOT NULL
              AND sm.actual_date IS NOT NULL
              AND DATE(sm.actual_date) BETWEEN :from AND :to
        ";
        $row   = $this->connection->fetchAssociative($sql, ['from' => $from, 'to' => $to]);
        $total = (int) ($row['total'] ?? 0);
        $on    = (int) ($row['on_time'] ?? 0);
        return [
            'total'       => $total,
            'on_time'     => $on,
            'delayed'     => (int) ($row['delayed'] ?? 0),
            'on_time_pct' => $total > 0 ? round($on / $total * 100, 1) : 0.0,
        ];
    }

    public function getConversionRate(string $from, string $to): array
    {
        $sql = "
            SELECT
                SUBSTR(q.created_date, 1, 7)                                          AS month,
                COUNT(*)                                                               AS total,
                SUM(CASE WHEN q.status = 'B' THEN 1 ELSE 0 END)                      AS converted,
                SUM(CASE WHEN q.status = 'R' THEN 1 ELSE 0 END)                      AS rejected
            FROM quote q
            WHERE DATE(q.created_date) BETWEEN :from AND :to
            GROUP BY SUBSTR(q.created_date, 1, 7)
            ORDER BY month ASC
        ";
        $rows = $this->connection->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
        return array_map(fn($r) => [
            ...$r,
            'conversion_pct' => (int) $r['total'] > 0
                ? round((int) $r['converted'] / (int) $r['total'] * 100, 1)
                : 0.0,
        ], $rows);
    }

    public function getOperatorProductivity(string $from, string $to): array
    {
        $sql = "
            SELECT
                COALESCE(u.first_name, '')                                                            AS first_name,
                COALESCE(u.last_name, '')                                                             AS last_name,
                COUNT(DISTINCT s.id)                                                                  AS jobs_count,
                COALESCE(SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END), 0) AS revenue_base,
                COALESCE(SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END), 0) AS cost_base,
                COALESCE(
                    SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END)
                    - SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END),
                    0
                )                                                                                     AS profit_base
            FROM shipment s
            LEFT JOIN user u ON u.id = s.account_manager_id
            LEFT JOIN ebit_note en ON en.shipment_id = s.id AND en.type IN ('ID', 'IC')
            WHERE s.status = 'CO'
              AND DATE(s.completed_date) BETWEEN :from AND :to
            GROUP BY s.account_manager_id, u.first_name, u.last_name
            ORDER BY jobs_count DESC
        ";
        return $this->connection->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
    }

    public function getDso(string $from, string $to): float
    {
        $sql = "
            SELECT COALESCE(AVG(DATEDIFF(rpt.created_date, en.created_date)), 0) AS avg_dso
            FROM ebit_note en
            JOIN ebit_note rpt ON rpt.parent_note_id = en.id AND rpt.type = 'RPT'
            WHERE en.type = 'ID'
              AND en.status != 'D'
              AND DATE(rpt.created_date) BETWEEN :from AND :to
        ";
        $result = $this->connection->fetchOne($sql, ['from' => $from, 'to' => $to]);
        return $result !== false ? round((float) $result, 1) : 0.0;
    }
}
