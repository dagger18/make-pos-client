<?php
namespace App\Module\Tax\Repository;

use Doctrine\DBAL\Connection;

class VatReportRepository
{
    public function __construct(private readonly Connection $conn) {}

    public function getOutputTax(string $from, string $to): array
    {
        $sql = "
            SELECT SUBSTR(en.note_date, 1, 7) AS tax_period,
                   ci.tax_code,
                   ci.tax_pct AS tax_rate,
                   SUM(ci.amount_amount)  AS taxable_amount,
                   SUM(ci.tax_amount)     AS tax_amount,
                   COUNT(DISTINCT en.id)  AS invoice_count
            FROM charge_item ci
            JOIN ebit_note en ON ci.ebit_note_id = en.id
            WHERE en.type = 'ID'
              AND en.status IN ('S','A','D')
              AND ci.is_exempt = 0
              AND ci.tax_code IS NOT NULL
              AND en.note_date BETWEEN :from AND :to
            GROUP BY SUBSTR(en.note_date, 1, 7), ci.tax_code, ci.tax_pct
            ORDER BY tax_period, ci.tax_code
        ";
        return $this->conn->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
    }

    public function getInputTax(string $from, string $to): array
    {
        $sql = "
            SELECT SUBSTR(en.note_date, 1, 7) AS tax_period,
                   ci.tax_code,
                   ci.tax_pct AS tax_rate,
                   SUM(ci.amount_amount)  AS taxable_amount,
                   SUM(ci.tax_amount)     AS tax_amount,
                   COUNT(DISTINCT en.id)  AS invoice_count
            FROM charge_item ci
            JOIN ebit_note en ON ci.ebit_note_id = en.id
            WHERE en.type = 'IC'
              AND en.status IN ('S','A','D')
              AND ci.is_exempt = 0
              AND ci.tax_code IS NOT NULL
              AND en.note_date BETWEEN :from AND :to
            GROUP BY SUBSTR(en.note_date, 1, 7), ci.tax_code, ci.tax_pct
            ORDER BY tax_period, ci.tax_code
        ";
        return $this->conn->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
    }

    public function getWithholdingTax(string $from, string $to): array
    {
        $sql = "
            SELECT SUBSTR(en.note_date, 1, 7) AS tax_period,
                   en.withholding_tax_ref,
                   en.withholding_tax_rate,
                   SUM(en.withholding_tax_amount) AS withholding_amount,
                   COUNT(en.id) AS invoice_count
            FROM ebit_note en
            WHERE en.type IN ('ID','IC')
              AND en.status IN ('S','A','D')
              AND en.withholding_tax_amount IS NOT NULL
              AND en.note_date BETWEEN :from AND :to
            GROUP BY SUBSTR(en.note_date, 1, 7), en.withholding_tax_ref, en.withholding_tax_rate
            ORDER BY tax_period
        ";
        return $this->conn->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
    }
}
