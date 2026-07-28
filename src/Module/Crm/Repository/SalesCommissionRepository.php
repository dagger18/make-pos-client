<?php
namespace App\Module\Crm\Repository;

use App\Module\Crm\Entity\SalesCommission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SalesCommissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalesCommission::class);
    }

    public function findByRep(int $repId, ?string $period = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('IDENTITY(c.salesRep) = :rid')
            ->setParameter('rid', $repId)
            ->orderBy('c.createdAt', 'DESC');

        if ($period) {
            $qb->andWhere('c.periodMonth = :period')->setParameter('period', $period);
        }
        return $qb->getQuery()->getResult();
    }

    public function save(SalesCommission $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }

    public function remove(SalesCommission $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }
}
