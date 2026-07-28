<?php

namespace App\Module\Operations\Repository;

use App\Module\Operations\Entity\ShipmentNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShipmentNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShipmentNote::class);
    }

    public function save(ShipmentNote $note): ShipmentNote
    {
        $this->getEntityManager()->persist($note);
        $this->getEntityManager()->flush();
        return $note;
    }

    public function delete(ShipmentNote $note): void
    {
        $this->getEntityManager()->remove($note);
        $this->getEntityManager()->flush();
    }

    /** @return ShipmentNote[] */
    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.shipment = :id')
            ->setParameter('id', $shipmentId)
            ->orderBy('n.isPinned', 'DESC')
            ->addOrderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
