<?php

namespace App\Module\Operations\Repository;

use App\Module\Operations\Entity\ShipmentParty;
use App\Module\Operations\Enum\PartyRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShipmentPartyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShipmentParty::class);
    }

    /** @return ShipmentParty[] */
    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.shipment = :sid')
            ->setParameter('sid', $shipmentId)
            ->leftJoin('p.client', 'c')->addSelect('c')
            ->leftJoin('p.contact', 'ct')->addSelect('ct')
            ->orderBy('p.role', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByShipmentAndRole(int $shipmentId, PartyRole $role): ?ShipmentParty
    {
        return $this->findOneBy(['shipment' => $shipmentId, 'role' => $role]);
    }

    public function save(ShipmentParty $party): void
    {
        $em = $this->getEntityManager();
        $em->persist($party);
        $em->flush();
    }

    public function delete(ShipmentParty $party): void
    {
        $em = $this->getEntityManager();
        $em->remove($party);
        $em->flush();
    }
}
