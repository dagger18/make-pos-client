<?php
namespace App\Module\Quote\Repository;

use App\Module\Quote\Entity\FreeTimeAgreement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FreeTimeAgreementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FreeTimeAgreement::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.carrier', 'c')
            ->leftJoin('f.port', 'p')
            ->addSelect('c', 'p')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('f.effectiveFrom', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByCarrier(int $carrierId): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.port', 'p')
            ->addSelect('p')
            ->where('f.carrier = :carrier')
            ->setParameter('carrier', $carrierId)
            ->orderBy('f.effectiveFrom', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(FreeTimeAgreement $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FreeTimeAgreement $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
