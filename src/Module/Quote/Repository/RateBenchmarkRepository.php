<?php
declare(strict_types=1);
namespace App\Module\Quote\Repository;

use Doctrine\DBAL\Connection;

class RateBenchmarkRepository
{
    public function __construct(private readonly Connection $connection) {}

    /**
     * Returns all active buy rates from the rate table, optionally filtered by mode and container type.
     * "Active" = valid_from <= today AND (valid_until IS NULL OR valid_until >= today).
     */
    public function getActiveBuyRates(?string $mode, ?string $containerType): array
    {
        $params = ['today' => date('Y-m-d')];
        $extra  = [];

        if ($mode) {
            $extra[] = 'r.transport_type = :mode';
            $params['mode'] = $mode;
        }
        if ($containerType) {
            $extra[] = 'r.container_type = :containerType';
            $params['containerType'] = $containerType;
        }

        $extraClause = $extra ? 'AND ' . implode(' AND ', $extra) : '';

        $sql = "
            SELECT
                p_pol.code            AS pol_code,
                p_pol.name            AS pol_name,
                p_pod.code            AS pod_code,
                p_pod.name            AS pod_name,
                pr.name               AS carrier,
                r.container_type,
                r.transport_type,
                r.buying_amount       AS buy_rate,
                r.buying_currency,
                DATE(r.valid_from)    AS valid_from,
                DATE(r.valid_until)   AS valid_until
            FROM rate r
            JOIN port p_pol    ON p_pol.id = r.pol_port_id
            JOIN port p_pod    ON p_pod.id = r.pod_port_id
            JOIN provider pr   ON pr.id    = r.provider_id
            WHERE r.buying_amount IS NOT NULL
              AND r.pol_port_id   IS NOT NULL
              AND r.pod_port_id   IS NOT NULL
              AND (r.valid_from   IS NULL OR DATE(r.valid_from)  <= :today)
              AND (r.valid_until  IS NULL OR DATE(r.valid_until) >= :today)
              {$extraClause}
            ORDER BY p_pol.code, p_pod.code, pr.name
        ";

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    /**
     * Returns sell comparison data grouped by POL/POD/mode for quotes created in the date range.
     * Status 'B' = Booked (the "won" status in QuoteStatus enum).
     */
    public function getSellComparison(string $dateFrom, string $dateTo, ?string $mode): array
    {
        $params = ['from' => $dateFrom, 'to' => $dateTo];
        $modeClause = '';
        if ($mode) {
            $modeClause = 'AND q.transport_type = :mode';
            $params['mode'] = $mode;
        }

        $sql = "
            SELECT
                p_pol.code                                                         AS pol_code,
                p_pod.code                                                         AS pod_code,
                q.transport_type,
                q.quote_currency,
                ROUND(AVG(q.total_selling), 2)                                     AS avg_sell,
                COUNT(DISTINCT q.id)                                               AS quotes_count,
                SUM(CASE WHEN q.status = 'B' THEN 1 ELSE 0 END)                   AS won,
                ROUND(
                    SUM(CASE WHEN q.status = 'B' THEN 1.0 ELSE 0 END)
                    / NULLIF(COUNT(DISTINCT q.id), 0) * 100, 1
                )                                                                  AS win_rate_pct
            FROM quote q
            JOIN port p_pol ON p_pol.id = q.origin_port_id
            JOIN port p_pod ON p_pod.id = q.destination_port_id
            WHERE DATE(q.created_date) BETWEEN :from AND :to
              AND q.origin_port_id      IS NOT NULL
              AND q.destination_port_id IS NOT NULL
              {$modeClause}
            GROUP BY p_pol.code, p_pod.code, q.transport_type, q.quote_currency
            ORDER BY avg_sell DESC
        ";

        return $this->connection->fetchAllAssociative($sql, $params);
    }
}
