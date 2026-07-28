<?php
declare(strict_types=1);
namespace App\Module\Emissions\Repository;

use App\Module\Emissions\Entity\ShipmentEmission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShipmentEmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShipmentEmission::class);
    }

    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('se')
            ->where('IDENTITY(se.shipment) = :sid')
            ->setParameter('sid', $shipmentId)
            ->orderBy('se.legSequence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return ShipmentEmission[] */
    public function findForReport(?\DateTime $from, ?\DateTime $to, ?string $transportMode): array
    {
        $qb = $this->createQueryBuilder('se')
            ->join('se.shipment', 's')
            ->orderBy('se.calculatedAt', 'DESC')
            ->setMaxResults(500);

        if ($from) {
            $qb->andWhere('se.calculatedAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('se.calculatedAt <= :to')->setParameter('to', $to);
        }
        if ($transportMode) {
            $qb->andWhere('se.transportMode = :mode')->setParameter('mode', $transportMode);
        }

        return $qb->getQuery()->getResult();
    }

    public function save(ShipmentEmission $e): void
    {
        $this->getEntityManager()->persist($e);
        $this->getEntityManager()->flush();
    }

    public function remove(ShipmentEmission $e): void
    {
        $this->getEntityManager()->remove($e);
        $this->getEntityManager()->flush();
    }
}
