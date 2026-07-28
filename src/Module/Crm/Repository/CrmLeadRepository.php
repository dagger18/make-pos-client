<?php
namespace App\Module\Crm\Repository;

use App\Module\Crm\Entity\CrmLead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CrmLeadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrmLead::class);
    }

    public function findByAssignee(int $userId): array
    {
        return $this->createQueryBuilder('l')
            ->where('IDENTITY(l.assignedTo) = :uid')
            ->setParameter('uid', $userId)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function save(CrmLead $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }

    public function remove(CrmLead $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }
}
