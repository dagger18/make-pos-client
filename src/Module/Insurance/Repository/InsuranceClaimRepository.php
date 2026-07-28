<?php
namespace App\Module\Insurance\Repository;

use App\Module\Insurance\Entity\InsuranceClaim;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InsuranceClaimRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InsuranceClaim::class);
    }

    public function findByCertificate(int $certificateId): array
    {
        return $this->createQueryBuilder('c')
            ->where('IDENTITY(c.certificate) = :cid')
            ->setParameter('cid', $certificateId)
            ->orderBy('c.incidentDate', 'DESC')
            ->getQuery()->getResult();
    }

    public function generateClaimNumber(): string
    {
        $year = date('Y');
        $conn = $this->getEntityManager()->getConnection();
        $count = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM insurance_claim WHERE SUBSTR(created_at, 1, 4) = ?",
            [$year]
        );
        return sprintf('CLM-%s-%05d', $year, $count + 1);
    }

    public function save(InsuranceClaim $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(InsuranceClaim $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
