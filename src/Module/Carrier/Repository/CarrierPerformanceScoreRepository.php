<?php
namespace App\Module\Carrier\Repository;

use App\Module\Carrier\Entity\CarrierPerformanceScore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CarrierPerformanceScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarrierPerformanceScore::class);
    }

    public function findOneForPeriod(int $carrierId, int $year, int $month, string $mode): ?CarrierPerformanceScore
    {
        return $this->findOneBy([
            'carrier'      => $carrierId,
            'periodYear'   => $year,
            'periodMonth'  => $month,
            'transportMode'=> $mode,
        ]);
    }

    public function findForPeriod(int $year, int $month, ?string $mode = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->join('s.carrier', 'c')
            ->addSelect('c')
            ->where('s.periodYear = :year')
            ->andWhere('s.periodMonth = :month')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->orderBy('s.compositeScore', 'DESC');

        if ($mode !== null) {
            $qb->andWhere('s.transportMode = :mode')->setParameter('mode', $mode);
        }

        return $qb->getQuery()->getResult();
    }

    public function findLatestForCarrier(int $carrierId, ?string $mode = null): ?CarrierPerformanceScore
    {
        $qb = $this->createQueryBuilder('s')
            ->where('IDENTITY(s.carrier) = :cid')
            ->setParameter('cid', $carrierId)
            ->orderBy('s.periodYear', 'DESC')
            ->addOrderBy('s.periodMonth', 'DESC')
            ->setMaxResults(1);

        if ($mode !== null) {
            $qb->andWhere('s.transportMode = :mode')->setParameter('mode', $mode);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findForCarrierHistory(int $carrierId, ?string $mode = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('IDENTITY(s.carrier) = :cid')
            ->setParameter('cid', $carrierId)
            ->orderBy('s.periodYear', 'DESC')
            ->addOrderBy('s.periodMonth', 'DESC')
            ->setMaxResults(24);

        if ($mode !== null) {
            $qb->andWhere('s.transportMode = :mode')->setParameter('mode', $mode);
        }

        return $qb->getQuery()->getResult();
    }

    public function save(CarrierPerformanceScore $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
