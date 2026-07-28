<?php
namespace App\Module\Reporting\Repository;

use Doctrine\DBAL\Connection;

class ReportRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function getCustomerProfitability(string $from, string $to): array
    {
        $sql = "
            SELECT
                COALESCE(c.name, 'Unknown')                                                           AS client_name,
                COUNT(DISTINCT s.id)                                                                  AS jobs_count,
                COALESCE(SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END), 0) AS revenue_base,
                COALESCE(SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END), 0) AS cost_base,
                COALESCE(
                    SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END)
                    - SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END),
                    0
                )                                                                                     AS profit_base
            FROM ebit_note en
            JOIN shipment s ON s.id = en.shipment_id
            LEFT JOIN client c ON c.id = en.collect_from_id
            WHERE en.type IN ('ID', 'IC')
              AND s.status = 'CO'
              AND DATE(s.completed_date) BETWEEN :from AND :to
            GROUP BY en.collect_from_id, c.name
            ORDER BY profit_base DESC
        ";
        $rows = $this->connection->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
        return array_map(fn($r) => [
            ...$r,
            'margin_pct' => (float) $r['revenue_base'] > 0
                ? round((float) $r['profit_base'] / (float) $r['revenue_base'] * 100, 1)
                : 0.0,
        ], $rows);
    }

    public function getTopLanes(string $from, string $to, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $sql = "
            SELECT
                COALESCE(po.name, '')                                                                  AS origin,
                COALESCE(pd.name, '')                                                                  AS destination,
                COUNT(DISTINCT s.id)                                                                   AS shipments,
                COALESCE(SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END), 0) AS revenue_base,
                COALESCE(
                    SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END)
                    - SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END),
                    0
                )                                                                                      AS profit_base
            FROM shipment s
            JOIN booking b ON b.id = s.booking_id
            LEFT JOIN port po ON po.id = b.port_loading_id
            LEFT JOIN port pd ON pd.id = b.port_discharge_id
            LEFT JOIN ebit_note en ON en.shipment_id = s.id AND en.type IN ('ID', 'IC')
            WHERE s.status = 'CO'
              AND DATE(s.completed_date) BETWEEN :from AND :to
              AND b.port_loading_id IS NOT NULL
              AND b.port_discharge_id IS NOT NULL
            GROUP BY b.port_loading_id, b.port_discharge_id, po.name, pd.name
            ORDER BY shipments DESC
            LIMIT " . $limit . "
        ";
        return $this->connection->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
    }

    public function getExceptions(string $from, string $to): array
    {
        $sql = "
            SELECT
                s.code                                    AS shipment_code,
                sm.milestone_code,
                sm.planned_date,
                sm.actual_date,
                sm.exception_hours,
                sm.remarks,
                COALESCE(u.first_name, '')               AS operator_first_name,
                COALESCE(u.last_name, '')                AS operator_last_name,
                COALESCE(po.name, '')                    AS origin,
                COALESCE(pd.name, '')                    AS destination
            FROM shipment_milestone sm
            JOIN shipment s ON s.id = sm.shipment_id
            LEFT JOIN booking b ON b.id = s.booking_id
            LEFT JOIN port po ON po.id = b.port_loading_id
            LEFT JOIN port pd ON pd.id = b.port_discharge_id
            LEFT JOIN user u ON u.id = s.account_manager_id
            WHERE sm.is_exception = 1
              AND DATE(sm.created_at) BETWEEN :from AND :to
            ORDER BY sm.created_at DESC
        ";
        return $this->connection->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
    }

    public function getOperationalDashboard(): array
    {
        $sql = "
            SELECT
                s.id,
                s.code,
                s.status,
                COALESCE(po.name, '')                    AS origin,
                COALESCE(pd.name, '')                    AS destination,
                b.etd,
                b.eta,
                COALESCE(u.first_name, '')               AS operator_first_name,
                COALESCE(u.last_name, '')                AS operator_last_name,
                (
                    SELECT COUNT(*)
                    FROM shipment_milestone sm2
                    WHERE sm2.shipment_id = s.id AND sm2.is_exception = 1
                )                                        AS exception_count
            FROM shipment s
            LEFT JOIN booking b ON b.id = s.booking_id
            LEFT JOIN port po ON po.id = b.port_loading_id
            LEFT JOIN port pd ON pd.id = b.port_discharge_id
            LEFT JOIN user u ON u.id = s.account_manager_id
            WHERE s.status IN ('PE', 'AC')
            ORDER BY b.etd ASC
        ";
        $shipments = $this->connection->fetchAllAssociative($sql);
        return [
            'active_count'    => count($shipments),
            'exception_count' => (int) array_sum(array_column($shipments, 'exception_count')),
            'shipments'       => $shipments,
        ];
    }

    public function getDashboardStats(): array
    {
        $shipments = $this->connection->fetchAllAssociative("
            SELECT
                s.id,
                s.code,
                s.status,
                COALESCE(po.name, '')      AS origin,
                COALESCE(pd.name, '')      AS destination,
                b.etd,
                b.eta,
                COALESCE(u.first_name, '') AS operator_first_name,
                COALESCE(u.last_name, '')  AS operator_last_name,
                COALESCE(q.transport_type, '') AS transport_type,
                (
                    SELECT COUNT(*)
                    FROM shipment_milestone sm2
                    WHERE sm2.shipment_id = s.id AND sm2.is_exception = 1
                ) AS exception_count
            FROM shipment s
            LEFT JOIN booking b   ON b.id  = s.booking_id
            LEFT JOIN quote q     ON q.id  = s.quote_id
            LEFT JOIN port po     ON po.id = b.port_loading_id
            LEFT JOIN port pd     ON pd.id = b.port_discharge_id
            LEFT JOIN user u      ON u.id  = s.account_manager_id
            WHERE s.status IN ('PE', 'AC')
            ORDER BY b.etd ASC
        ");

        $nowStr    = date('Y-m-d H:i:s');
        $in7Days   = date('Y-m-d H:i:s', strtotime('+7 days'));

        $pendingCount     = 0;
        $confirmedCount   = 0;
        $exceptionCount   = 0;
        $overdueCount     = 0;
        $arrivingSoon     = [];

        foreach ($shipments as $s) {
            if ($s['status'] === 'PE') $pendingCount++;
            if ($s['status'] === 'AC') $confirmedCount++;
            $exceptionCount += (int) $s['exception_count'];
            if ($s['eta']) {
                if ($s['eta'] < $nowStr) {
                    $overdueCount++;
                } elseif ($s['eta'] <= $in7Days) {
                    $arrivingSoon[] = $s;
                }
            }
        }

        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-d');

        $completedThisMonth = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM shipment WHERE status = 'CO' AND DATE(completed_date) BETWEEN :from AND :to",
            ['from' => $monthStart, 'to' => $monthEnd]
        );

        $byMode = $this->connection->fetchAllAssociative("
            SELECT COALESCE(q.transport_type, '') AS transport_type, COUNT(*) AS cnt
            FROM shipment s
            LEFT JOIN quote q ON q.id = s.quote_id
            WHERE s.status IN ('PE', 'AC')
            GROUP BY q.transport_type
            ORDER BY cnt DESC
        ");

        $monthlyTrend = $this->connection->fetchAllAssociative("
            SELECT strftime('%Y-%m', s.created_date) AS month, COUNT(*) AS cnt
            FROM shipment s
            WHERE s.created_date >= date('now', '-6 months')
              AND s.status NOT IN ('DR', 'CA', 'DE')
            GROUP BY strftime('%Y-%m', s.created_date)
            ORDER BY month ASC
        ");

        return [
            'active_count'         => count($shipments),
            'pending_count'        => $pendingCount,
            'confirmed_count'      => $confirmedCount,
            'exception_count'      => $exceptionCount,
            'overdue_count'        => $overdueCount,
            'arriving_soon_count'  => count($arrivingSoon),
            'completed_this_month' => $completedThisMonth,
            'by_mode'              => $byMode,
            'monthly_trend'        => $monthlyTrend,
            'shipments'            => $shipments,
            'upcoming_arrivals'    => array_slice($arrivingSoon, 0, 10),
        ];
    }
}
