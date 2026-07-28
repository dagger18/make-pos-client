<?php
declare(strict_types=1);
namespace App\Module\Lc\Repository;

use App\Module\Lc\Entity\LcDiscrepancy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LcDiscrepancyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LcDiscrepancy::class);
    }

    public function findByLc(int $lcId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.lc = :lcId')
            ->setParameter('lcId', $lcId)
            ->orderBy('d.detectedAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function countOpen(int $lcId): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.lc = :lcId')
            ->andWhere('d.isWaived = false')
            ->andWhere('d.resolvedAt IS NULL')
            ->setParameter('lcId', $lcId)
            ->getQuery()->getSingleScalarResult();
    }
}
