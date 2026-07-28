<?php
namespace App\Module\Notification\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Notification\Entity\InAppNotification;

class InAppNotificationRepository extends BaseRepository
{
    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }

    public function findPagedForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $items = $this->createQueryBuilder('n')
            ->where('n.user = :uid')
            ->setParameter('uid', $userId)
            ->orderBy('n.createdDate', 'DESC')
            ->setMaxResults($perPage)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        $total = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'list'        => $items,
            'currentPage' => $page,
            'totalPages'  => (int) ceil($total / $perPage),
            'total'       => $total,
        ];
    }

    public function countUnreadForUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :uid')
            ->andWhere('n.isRead = false')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markReadForUser(int $userId, ?array $ids = null): void
    {
        $qb = $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.readAt', ':now')
            ->where('n.user = :uid')
            ->andWhere('n.isRead = false')
            ->setParameter('uid', $userId)
            ->setParameter('now', new \DateTime());

        if ($ids !== null) {
            $qb->andWhere('n.id IN (:ids)')->setParameter('ids', $ids);
        }

        $qb->getQuery()->execute();
    }
}
