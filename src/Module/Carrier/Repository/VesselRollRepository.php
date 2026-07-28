<?php
declare(strict_types=1);
namespace App\Module\Carrier\Repository;

use App\Module\Carrier\Entity\VesselRoll;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VesselRollRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VesselRoll::class);
    }

    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('vr')
            ->where('vr.shipment = :shipmentId')
            ->setParameter('shipmentId', $shipmentId)
            ->orderBy('vr.rolledAt', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }
}
