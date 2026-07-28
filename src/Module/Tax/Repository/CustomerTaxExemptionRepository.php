<?php
namespace App\Module\Tax\Repository;

use App\Module\Tax\Entity\CustomerTaxExemption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CustomerTaxExemptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerTaxExemption::class);
    }

    public function findForPartner(int $partnerId, string $countryCode, string $date): ?CustomerTaxExemption
    {
        return $this->createQueryBuilder('e')
            ->where('IDENTITY(e.partner) = :pid')
            ->andWhere('e.countryCode = :cc')
            ->andWhere('e.validFrom <= :date')
            ->andWhere('e.validTo IS NULL OR e.validTo >= :date')
            ->setParameter('pid', $partnerId)
            ->setParameter('cc', $countryCode)
            ->setParameter('date', $date)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByPartner(int $partnerId): array
    {
        return $this->findBy(['partner' => $partnerId], ['validFrom' => 'DESC']);
    }

    public function save(CustomerTaxExemption $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CustomerTaxExemption $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
