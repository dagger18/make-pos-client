<?php

namespace App\Module\Operations\Repository;

use App\Module\Operations\Entity\ShipmentMilestone;
use App\Module\Carrier\Enum\MilestoneCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShipmentMilestoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShipmentMilestone::class);
    }

    /** @return ShipmentMilestone[] */
    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.shipment = :sid')
            ->setParameter('sid', $shipmentId)
            ->leftJoin('m.updatedBy', 'u')->addSelect('u')
            ->orderBy('CASE WHEN m.actualDate IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('m.actualDate', 'DESC')
            ->addOrderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByShipmentAndCode(int $shipmentId, MilestoneCode $code): ?ShipmentMilestone
    {
        return $this->findOneBy(['shipment' => $shipmentId, 'milestoneCode' => $code]);
    }

    public function save(ShipmentMilestone $milestone): void
    {
        $em = $this->getEntityManager();
        $em->persist($milestone);
        $em->flush();
    }

    public function delete(ShipmentMilestone $milestone): void
    {
        $em = $this->getEntityManager();
        $em->remove($milestone);
        $em->flush();
    }
}
