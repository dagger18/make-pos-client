<?php

namespace App\Module\Operations\Repository;

use App\Module\Operations\Entity\ShipmentDocument;
use App\Module\Operations\Enum\DocType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShipmentDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShipmentDocument::class);
    }

    public function save(ShipmentDocument $doc): ShipmentDocument
    {
        $this->getEntityManager()->persist($doc);
        $this->getEntityManager()->flush();
        return $doc;
    }

    public function delete(ShipmentDocument $doc): void
    {
        $this->getEntityManager()->remove($doc);
        $this->getEntityManager()->flush();
    }

    /** @return ShipmentDocument[] */
    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.shipment = :id')
            ->setParameter('id', $shipmentId)
            ->orderBy('d.isRequired', 'DESC')
            ->addOrderBy('d.docType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function existsForShipmentAndType(int $shipmentId, DocType $docType): bool
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.shipment = :id AND d.docType = :type')
            ->setParameter('id', $shipmentId)
            ->setParameter('type', $docType->value)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
