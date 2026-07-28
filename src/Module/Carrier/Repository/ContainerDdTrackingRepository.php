<?php
namespace App\Module\Carrier\Repository;

use App\Module\Carrier\Entity\ContainerDdTracking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContainerDdTrackingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContainerDdTracking::class);
    }

    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.freeTimeAgreement', 'f')
            ->addSelect('f')
            ->where('d.shipment = :shipment')
            ->setParameter('shipment', $shipmentId)
            ->orderBy('d.containerNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAccruing(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.freeTimeAgreement', 'f')
            ->addSelect('f')
            ->where('d.isFinal = :false')
            ->andWhere('d.freeEndDate < :today')
            ->setParameter('false', false)
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getResult();
    }

    public function findDashboard(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.shipment', 's')
            ->leftJoin('d.freeTimeAgreement', 'f')
            ->addSelect('s', 'f')
            ->where('d.isFinal = :false')
            ->setParameter('false', false)
            ->orderBy('d.accruedAmount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(ContainerDdTracking $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ContainerDdTracking $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
