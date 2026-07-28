<?php
namespace App\Module\Crm\Repository;

use App\Module\Crm\Entity\SalesTarget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SalesTargetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalesTarget::class);
    }

    public function findByYear(int $year): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.periodYear = :year')
            ->setParameter('year', $year)
            ->orderBy('t.periodMonth', 'ASC')
            ->getQuery()->getResult();
    }

    public function save(SalesTarget $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }

    public function remove(SalesTarget $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }
}
