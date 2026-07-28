<?php
namespace App\Module\Finance\Repository;

use Doctrine\DBAL\Connection;

class AgeingRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function getArAgeing(): array
    {
        $sql = "
            SELECT
                COALESCE(c.name, pr.name, 'Unknown') AS partner,
                en.currency,
                SUM(en.amount_amount)                                                        AS total_invoiced,
                COALESCE(SUM(paid.paid_amount), 0)                                           AS total_paid,
                SUM(en.amount_amount) - COALESCE(SUM(paid.paid_amount), 0)                  AS outstanding,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) <= 0
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS current_not_due,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) BETWEEN 1  AND 30
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_1_30,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) BETWEEN 31 AND 60
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_31_60,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) BETWEEN 61 AND 90
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_61_90,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) > 90
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_90plus
            FROM ebit_note en
            LEFT JOIN partner p ON p.id = en.collect_from_id
            LEFT JOIN client c ON c.id = en.collect_from_id
            LEFT JOIN provider pr ON pr.id = en.collect_from_id
            LEFT JOIN (
                SELECT parent_note_id, SUM(amount_amount) AS paid_amount
                FROM ebit_note
                WHERE type = 'RPT'
                GROUP BY parent_note_id
            ) paid ON paid.parent_note_id = en.id
            WHERE en.type = 'ID'
              AND en.status != 'D'
            GROUP BY en.collect_from_id, c.name, pr.name, en.currency
            HAVING outstanding > 0
            ORDER BY outstanding DESC
        ";
        return $this->connection->fetchAllAssociative($sql);
    }

    public function getApAgeing(): array
    {
        $sql = "
            SELECT
                COALESCE(c.name, pr.name, 'Unknown') AS partner,
                en.currency,
                SUM(en.amount_amount)                                                        AS total_billed,
                COALESCE(SUM(paid.paid_amount), 0)                                           AS total_paid,
                SUM(en.amount_amount) - COALESCE(SUM(paid.paid_amount), 0)                  AS outstanding,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) <= 0
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS current_not_due,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) BETWEEN 1  AND 30
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_1_30,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) BETWEEN 31 AND 60
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_31_60,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) BETWEEN 61 AND 90
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_61_90,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) > 90
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_90plus
            FROM ebit_note en
            LEFT JOIN partner p ON p.id = en.pay_to_id
            LEFT JOIN client c ON c.id = en.pay_to_id
            LEFT JOIN provider pr ON pr.id = en.pay_to_id
            LEFT JOIN (
                SELECT parent_note_id, SUM(amount_amount) AS paid_amount
                FROM ebit_note
                WHERE type = 'PMT'
                GROUP BY parent_note_id
            ) paid ON paid.parent_note_id = en.id
            WHERE en.type = 'IC'
              AND en.status != 'D'
            GROUP BY en.pay_to_id, c.name, pr.name, en.currency
            HAVING outstanding > 0
            ORDER BY outstanding DESC
        ";
        return $this->connection->fetchAllAssociative($sql);
    }

    public function getClientExposure(int $clientId, string $currency): float
    {
        $sql = "
            SELECT COALESCE(
                SUM(en.amount_amount) - COALESCE(SUM(paid.paid_amount), 0),
                0
            ) AS outstanding
            FROM ebit_note en
            LEFT JOIN (
                SELECT parent_note_id, SUM(amount_amount) AS paid_amount
                FROM ebit_note
                WHERE type = 'RPT'
                GROUP BY parent_note_id
            ) paid ON paid.parent_note_id = en.id
            WHERE en.type = 'ID'
              AND en.status != 'D'
              AND en.collect_from_id = :clientId
              AND en.currency = :currency
        ";
        $result = $this->connection->fetchOne($sql, ['clientId' => $clientId, 'currency' => $currency]);
        return $result !== false ? (float) $result : 0.0;
    }

    public function getClientsWithOverdueData(): array
    {
        $sql = "
            SELECT
                en.collect_from_id AS client_id,
                en.currency,
                MAX(DATEDIFF(CURDATE(), en.due_date)) AS max_days_overdue,
                SUM(en.amount_amount) - COALESCE(SUM(paid.paid_amount), 0) AS outstanding
            FROM ebit_note en
            LEFT JOIN (
                SELECT parent_note_id, SUM(amount_amount) AS paid_amount
                FROM ebit_note
                WHERE type = 'RPT'
                GROUP BY parent_note_id
            ) paid ON paid.parent_note_id = en.id
            WHERE en.type = 'ID'
              AND en.status != 'D'
              AND DATEDIFF(CURDATE(), en.due_date) > 0
            GROUP BY en.collect_from_id, en.currency
            HAVING outstanding > 0
            ORDER BY max_days_overdue DESC
        ";
        return $this->connection->fetchAllAssociative($sql);
    }
}
