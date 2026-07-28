<?php
namespace App\Module\Sales\Repository;

use App\Module\Core\Repository\BaseRepository;
use App\Module\Sales\Entity\Order;

class OrderRepository extends BaseRepository
{
    /**
     * @return Order[]
     */
    public function findByFilters(?string $status, ?string $dateFrom, ?string $dateTo, ?string $q): array
    {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.createdDate', 'DESC')
            ->setMaxResults(200);

        if ($status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }
        if ($dateFrom) {
            $qb->andWhere('o.createdDate >= :from')->setParameter('from', new \DateTime($dateFrom . ' 00:00:00'));
        }
        if ($dateTo) {
            $qb->andWhere('o.createdDate <= :to')->setParameter('to', new \DateTime($dateTo . ' 23:59:59'));
        }
        if ($q) {
            $qb->andWhere('o.id = :id OR o.notes LIKE :q')
               ->setParameter('id', is_numeric($q) ? (int) $q : 0)
               ->setParameter('q', '%' . $q . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
