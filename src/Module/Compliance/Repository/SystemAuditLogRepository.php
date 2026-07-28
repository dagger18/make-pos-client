<?php
declare(strict_types=1);
namespace App\Module\Compliance\Repository;

use App\Module\Compliance\Entity\SystemAuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemAuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemAuditLog::class);
    }

    public function insert(SystemAuditLog $log): void
    {
        $this->getEntityManager()->persist($log);
        $this->getEntityManager()->flush();
    }

    /** @return SystemAuditLog[] */
    public function search(
        ?string    $eventType = null,
        ?string    $actorEmail = null,
        ?int       $actorId = null,
        ?string    $objectType = null,
        ?int       $objectId = null,
        ?string    $result = null,
        ?\DateTime $from = null,
        ?\DateTime $to = null,
        int        $limit = 200,
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.loggedAt', 'DESC')
            ->setMaxResults($limit);

        if ($eventType)   $qb->andWhere('l.eventType LIKE :et')->setParameter('et', $eventType . '%');
        if ($actorEmail)  $qb->andWhere('l.actorEmail LIKE :ae')->setParameter('ae', '%' . $actorEmail . '%');
        if ($actorId)     $qb->andWhere('l.actorId = :ai')->setParameter('ai', $actorId);
        if ($objectType)  $qb->andWhere('l.objectType = :ot')->setParameter('ot', $objectType);
        if ($objectId)    $qb->andWhere('l.objectId = :oi')->setParameter('oi', $objectId);
        if ($result)      $qb->andWhere('l.result = :res')->setParameter('res', $result);
        if ($from)        $qb->andWhere('l.loggedAt >= :from')->setParameter('from', $from);
        if ($to)          $qb->andWhere('l.loggedAt <= :to')->setParameter('to', $to);

        return $qb->getQuery()->getResult();
    }

    /** Monthly summary grouped by event_type and result for the compliance dashboard */
    public function getMonthlyStats(int $months = 12): array
    {
        $from = (new \DateTime())->modify("-{$months} months");
        return $this->getEntityManager()->createNativeQuery(
            "SELECT
                DATE_FORMAT(logged_at, '%Y-%m') AS period,
                event_type,
                result,
                COUNT(*) AS event_count
             FROM system_audit_log
             WHERE logged_at >= :from
             GROUP BY DATE_FORMAT(logged_at, '%Y-%m'), event_type, result
             ORDER BY period DESC, event_count DESC",
            new \Doctrine\ORM\Query\ResultSetMapping()
        )->setParameter('from', $from->format('Y-m-d'))
         ->getResult(\Doctrine\ORM\Query::HYDRATE_SCALAR);
    }

    public function getComplianceEventStats(int $months = 12): array
    {
        $from = (new \DateTime())->modify("-{$months} months");
        $conn = $this->getEntityManager()->getConnection();
        return $conn->fetchAllAssociative(
            "SELECT
                DATE_FORMAT(logged_at, '%Y-%m') AS period,
                event_type,
                result,
                COUNT(*) AS event_count
             FROM system_audit_log
             WHERE event_type LIKE 'COMPLIANCE.%'
               AND logged_at >= :from
             GROUP BY DATE_FORMAT(logged_at, '%Y-%m'), event_type, result
             ORDER BY period DESC, event_count DESC",
            ['from' => $from->format('Y-m-d')]
        );
    }

    public function getTotals(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        return $conn->fetchAssociative(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN event_type LIKE 'AUTH.%' THEN 1 ELSE 0 END) AS auth_events,
                SUM(CASE WHEN event_type LIKE 'COMPLIANCE.%' THEN 1 ELSE 0 END) AS compliance_events,
                SUM(CASE WHEN event_type LIKE 'FINANCIAL.%' THEN 1 ELSE 0 END) AS financial_events,
                SUM(CASE WHEN result = 'BLOCKED' THEN 1 ELSE 0 END) AS blocked_events,
                SUM(CASE WHEN result = 'FAILURE' THEN 1 ELSE 0 END) AS failure_events
             FROM system_audit_log
             WHERE logged_at >= :from",
            ['from' => (new \DateTime())->modify('-30 days')->format('Y-m-d')]
        ) ?: [];
    }
}
