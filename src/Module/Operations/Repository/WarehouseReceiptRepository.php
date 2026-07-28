<?php

namespace App\Module\Operations\Repository;

use App\Module\Operations\Entity\WarehouseReceipt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WarehouseReceiptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarehouseReceipt::class);
    }

    public function save(WarehouseReceipt $receipt): WarehouseReceipt
    {
        $this->getEntityManager()->persist($receipt);
        $this->getEntityManager()->flush();
        return $receipt;
    }

    public function delete(WarehouseReceipt $receipt): void
    {
        $this->getEntityManager()->remove($receipt);
        $this->getEntityManager()->flush();
    }

    /** @return WarehouseReceipt[] */
    public function findByShipment(int $shipmentId): array
    {
        return $this->findBy(['shipment' => $shipmentId], ['createdAt' => 'DESC']);
    }

    /** @return WarehouseReceipt[] */
    public function findByFacility(int $facilityId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.facilityId = :fid AND r.releasedAt IS NULL')
            ->setParameter('fid', $facilityId)
            ->orderBy('r.receivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
