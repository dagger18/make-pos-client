<?php
namespace App\Module\Crm\Repository;

use App\Module\Crm\Entity\CrmActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CrmActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrmActivity::class);
    }

    public function findByOpportunity(int $opportunityId): array
    {
        return $this->createQueryBuilder('a')
            ->where('IDENTITY(a.opportunity) = :oid')
            ->setParameter('oid', $opportunityId)
            ->orderBy('a.performedAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function findUpcomingActions(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.nextActionDate >= :today')
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('a.nextActionDate', 'ASC')
            ->setMaxResults(50)
            ->getQuery()->getResult();
    }

    public function save(CrmActivity $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }

    public function remove(CrmActivity $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }
}
