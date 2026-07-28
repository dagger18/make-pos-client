<?php
namespace App\Module\Finance\Repository;

use App\Module\Finance\Entity\TaxRule;
use Doctrine\DBAL\Connection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TaxRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly Connection $conn)
    {
        parent::__construct($registry, TaxRule::class);
    }

    public function findAllOrdered(): array
    {
        return $this->findBy([], ['countryCode' => 'ASC', 'taxCode' => 'ASC']);
    }

    public function findMostSpecific(
        string $countryCode,
        ?string $chargeCategory,
        ?string $serviceType,
        ?string $customerType,
        string $date
    ): ?TaxRule {
        $sql = "
            SELECT id,
                (CASE WHEN charge_category = :chargeCategory THEN 4 ELSE 0 END) +
                (CASE WHEN service_type    = :serviceType    THEN 2 ELSE 0 END) +
                (CASE WHEN customer_type   = :customerType   THEN 1 ELSE 0 END) AS specificity
            FROM tax_rule
            WHERE country_code = :countryCode
              AND (charge_category IS NULL OR charge_category = :chargeCategory)
              AND (service_type    IS NULL OR service_type    = :serviceType)
              AND (customer_type   IS NULL OR customer_type   = :customerType)
              AND effective_from <= :date
              AND (effective_to IS NULL OR effective_to >= :date)
            ORDER BY specificity DESC
            LIMIT 1
        ";
        $row = $this->conn->fetchAssociative($sql, [
            'countryCode'    => $countryCode,
            'chargeCategory' => $chargeCategory,
            'serviceType'    => $serviceType,
            'customerType'   => $customerType,
            'date'           => $date,
        ]);
        if (!$row) {
            return null;
        }
        return $this->find($row['id']);
    }

    public function save(TaxRule $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TaxRule $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
