<?php
namespace App\Module\Notification\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Notification\Entity\NotificationRule;

class NotificationRuleRepository extends BaseRepository
{
    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }

    public function findActiveByTriggerType(string $triggerType): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.triggerType = :type')
            ->andWhere('r.isActive = true')
            ->setParameter('type', $triggerType)
            ->getQuery()
            ->getResult();
    }

    public function findActiveDeadlineRules(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.triggerType = :type')
            ->andWhere('r.isActive = true')
            ->setParameter('type', 'DEADLINE')
            ->getQuery()
            ->getResult();
    }

    public function findActiveFinancialRules(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.triggerType = :type')
            ->andWhere('r.isActive = true')
            ->setParameter('type', 'FINANCIAL')
            ->getQuery()
            ->getResult();
    }
}
