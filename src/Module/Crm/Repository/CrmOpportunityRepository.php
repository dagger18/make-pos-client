<?php
namespace App\Module\Crm\Repository;

use App\Module\Crm\Entity\CrmOpportunity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CrmOpportunityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrmOpportunity::class);
    }

    public function findPipeline(?int $assigneeId = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where("o.stage NOT IN ('CLOSED_WON', 'CLOSED_LOST')")
            ->orderBy('o.expectedClose', 'ASC');

        if ($assigneeId) {
            $qb->andWhere('IDENTITY(o.assignedTo) = :uid')->setParameter('uid', $assigneeId);
        }
        return $qb->getQuery()->getResult();
    }

    public function getPipelineSummary(): array
    {
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT stage,
                    COUNT(*) AS count,
                    COALESCE(SUM(estimated_revenue), 0) AS total_revenue,
                    COALESCE(SUM(estimated_revenue * probability_pct / 100), 0) AS weighted_revenue
             FROM crm_opportunity
             WHERE stage NOT IN ('CLOSED_WON','CLOSED_LOST')
             GROUP BY stage
             ORDER BY FIELD(stage,'PROSPECTING','QUALIFICATION','PROPOSAL','NEGOTIATION')"
        );
    }

    public function save(CrmOpportunity $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }

    public function remove(CrmOpportunity $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }
}
