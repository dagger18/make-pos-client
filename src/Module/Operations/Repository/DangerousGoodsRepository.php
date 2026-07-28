<?php

namespace App\Module\Operations\Repository;

use App\Module\Operations\Entity\DangerousGoods;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DangerousGoodsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DangerousGoods::class);
    }

    public function save(DangerousGoods $dg): DangerousGoods
    {
        $this->getEntityManager()->persist($dg);
        $this->getEntityManager()->flush();
        return $dg;
    }

    public function delete(DangerousGoods $dg): void
    {
        $this->getEntityManager()->remove($dg);
        $this->getEntityManager()->flush();
    }

    /** @return DangerousGoods[] */
    public function findByShipment(int $shipmentId): array
    {
        return $this->findBy(['shipment' => $shipmentId]);
    }
}
