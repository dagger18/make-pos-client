<?php
namespace App\Module\Integration\Service;

use App\Module\Crm\Entity\Client;
use App\Module\Operations\Entity\ShipmentParty;
use App\Module\Operations\Repository\ShipmentRepository;

class PortalShipmentService
{
    public function __construct(
        private readonly ShipmentRepository $shipmentRepository,
    ) {}

    public function getShipmentsForClient(Client $client, int $limit = 50, int $offset = 0): array
    {
        return $this->shipmentRepository->createQueryBuilder('s')
            ->innerJoin(ShipmentParty::class, 'p', 'WITH', 'p.shipment = s AND p.client = :client')
            ->setParameter('client', $client)
            ->orderBy('s.createdDate', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function getShipmentForClient(int $id, Client $client): ?object
    {
        return $this->shipmentRepository->createQueryBuilder('s')
            ->innerJoin(ShipmentParty::class, 'p', 'WITH', 'p.shipment = s AND p.client = :client')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->setParameter('client', $client)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
