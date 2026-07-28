<?php
declare(strict_types=1);
namespace App\Module\Lc\Repository;

use App\Module\Lc\Entity\LcDocumentRequirement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LcDocumentRequirementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LcDocumentRequirement::class);
    }

    public function findByLc(int $lcId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.lc = :lcId')
            ->setParameter('lcId', $lcId)
            ->orderBy('d.docType', 'ASC')
            ->getQuery()->getResult();
    }

    public function countPending(int $lcId): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.lc = :lcId')
            ->andWhere('d.isReady = false')
            ->setParameter('lcId', $lcId)
            ->getQuery()->getSingleScalarResult();
    }
}
