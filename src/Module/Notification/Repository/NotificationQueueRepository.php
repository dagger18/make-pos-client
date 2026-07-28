<?php
namespace App\Module\Notification\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Notification\Entity\NotificationQueue;

class NotificationQueueRepository extends BaseRepository
{
    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }

    public function findPendingDue(int $limit = 50): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.status = :status')
            ->andWhere('q.scheduledAt <= :now')
            ->setParameter('status', 'PENDING')
            ->setParameter('now', new \DateTime())
            ->orderBy('q.priority', 'DESC')
            ->addOrderBy('q.scheduledAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
