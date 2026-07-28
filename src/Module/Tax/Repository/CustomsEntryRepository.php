<?php
namespace App\Module\Tax\Repository;

use App\Module\Core\Repository\BaseRepository;

class CustomsEntryRepository extends BaseRepository
{
    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('ce')
            ->leftJoin('ce.lines', 'l')
            ->addSelect('l')
            ->where('ce.shipmentId = :sid')
            ->setParameter('sid', $shipmentId)
            ->orderBy('ce.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
