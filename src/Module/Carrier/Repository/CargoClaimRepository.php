<?php
namespace App\Module\Carrier\Repository;

use App\Module\Carrier\Entity\CargoClaim;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CargoClaimRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CargoClaim::class);
    }

    public function findByCarrier(int $carrierId): array
    {
        return $this->createQueryBuilder('c')
            ->where('IDENTITY(c.carrier) = :cid')
            ->setParameter('cid', $carrierId)
            ->orderBy('c.claimDate', 'DESC')
            ->getQuery()->getResult();
    }

    /**
     * Returns [carrierId => ['claimCount' => n, 'shipmentCount' => m], ...] for the period.
     * shipmentCount is the total distinct shipments the carrier handled (from IC ebit notes),
     * not just shipments that had claims — giving a correct denominator for claimsPer100.
     */
    public function aggregateForPeriod(int $year, int $month, string $mode): array
    {
        $conn      = $this->getEntityManager()->getConnection();
        $yearStr   = str_pad((string) $year,  4, '0', STR_PAD_LEFT);
        $monthStr  = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        // Total distinct shipments per carrier from IC-type ebit notes for the period.
        // Uses SUBSTR for SQLite compatibility (YEAR()/MONTH() not supported in SQLite).
        $shipmentRows = $conn->fetchAllAssociative(
            "SELECT ci.provider_id AS carrier_id, COUNT(DISTINCT en.shipment_id) AS total
             FROM charge_item ci
             JOIN ebit_note en ON ci.ebit_note_id = en.id
             WHERE en.type = 'IC'
               AND SUBSTR(en.note_date, 1, 4) = :year
               AND SUBSTR(en.note_date, 6, 2) = :month
             GROUP BY ci.provider_id",
            ['year' => $yearStr, 'month' => $monthStr]
        );
        $shipmentTotals = [];
        foreach ($shipmentRows as $row) {
            $shipmentTotals[(int) $row['carrier_id']] = (int) $row['total'];
        }

        // Claim count per carrier for the mode + period.
        $claimRows = $conn->fetchAllAssociative(
            "SELECT carrier_id, COUNT(*) AS claim_count
             FROM cargo_claim
             WHERE transport_mode = :mode
               AND SUBSTR(claim_date, 1, 4) = :year
               AND SUBSTR(claim_date, 6, 2) = :month
             GROUP BY carrier_id",
            ['mode' => $mode, 'year' => $yearStr, 'month' => $monthStr]
        );

        $result = [];
        foreach ($claimRows as $row) {
            $carrierId = (int) $row['carrier_id'];
            $result[$carrierId] = [
                'claimCount'    => (int) $row['claim_count'],
                'shipmentCount' => $shipmentTotals[$carrierId] ?? 0,
            ];
        }
        return $result;
    }

    public function save(CargoClaim $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CargoClaim $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
