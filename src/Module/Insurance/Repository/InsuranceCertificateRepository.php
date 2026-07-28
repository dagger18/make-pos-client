<?php
namespace App\Module\Insurance\Repository;

use App\Module\Insurance\Entity\InsuranceCertificate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InsuranceCertificateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InsuranceCertificate::class);
    }

    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('c')
            ->where('IDENTITY(c.shipment) = :sid')
            ->setParameter('sid', $shipmentId)
            ->orderBy('c.issueDate', 'DESC')
            ->getQuery()->getResult();
    }

    public function findByPolicyAndPeriod(int $policyId, string $from, string $to): array
    {
        return $this->createQueryBuilder('c')
            ->where('IDENTITY(c.policy) = :pid')
            ->andWhere('c.issueDate BETWEEN :from AND :to')
            ->setParameter('pid', $policyId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.issueDate', 'ASC')
            ->getQuery()->getResult();
    }

    public function generateCertificateNumber(): string
    {
        $year = date('Y');
        $conn = $this->getEntityManager()->getConnection();
        $count = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM insurance_certificate WHERE SUBSTR(issue_date, 1, 4) = ?",
            [$year]
        );
        return sprintf('CERT-%s-%05d', $year, $count + 1);
    }

    public function save(InsuranceCertificate $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(InsuranceCertificate $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
