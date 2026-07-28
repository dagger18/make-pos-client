<?php

namespace App\Module\Operations\Repository;

use App\Module\Operations\Entity\ShipmentTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShipmentTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShipmentTask::class);
    }

    /** @return ShipmentTask[] */
    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.shipment = :sid')
            ->setParameter('sid', $shipmentId)
            ->leftJoin('t.assignedTo',  'a')->addSelect('a')
            ->leftJoin('t.completedBy', 'c')->addSelect('c')
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return ShipmentTask[] Incomplete mandatory tasks for the given milestone gate */
    public function findBlockingTasks(int $shipmentId, string $milestoneGate): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.shipment = :sid')
            ->andWhere('t.milestoneGate = :gate')
            ->andWhere('t.isMandatory = true')
            ->andWhere('t.completedAt IS NULL')
            ->setParameter('sid', $shipmentId)
            ->setParameter('gate', $milestoneGate)
            ->getQuery()
            ->getResult();
    }

    public function hasTasksForShipment(int $shipmentId): bool
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.shipment = :sid')
            ->setParameter('sid', $shipmentId)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function save(ShipmentTask $task): void
    {
        $em = $this->getEntityManager();
        $em->persist($task);
        $em->flush();
    }

    public function saveAll(array $tasks): void
    {
        $em = $this->getEntityManager();
        foreach ($tasks as $task) {
            $em->persist($task);
        }
        $em->flush();
    }

    public function delete(ShipmentTask $task): void
    {
        $em = $this->getEntityManager();
        $em->remove($task);
        $em->flush();
    }
}
