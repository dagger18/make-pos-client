<?php
declare(strict_types=1);
namespace App\Module\Emissions\Repository;

use App\Module\Emissions\Entity\EmissionFactor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EmissionFactorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmissionFactor::class);
    }

    public function findBestMatch(string $transportMode, ?string $vehicleType = null, ?string $sizeClass = null): ?EmissionFactor
    {
        $qb = $this->createQueryBuilder('ef')
            ->where('ef.transportMode = :mode')
            ->andWhere('ef.effectiveFrom <= :today')
            ->andWhere('ef.effectiveTo IS NULL OR ef.effectiveTo >= :today')
            ->setParameter('mode', $transportMode)
            ->setParameter('today', new \DateTime())
            ->orderBy('ef.effectiveFrom', 'DESC');

        if ($vehicleType !== null) {
            $qb->andWhere('ef.vehicleType = :vt')->setParameter('vt', $vehicleType);
        }
        if ($sizeClass !== null) {
            $qb->andWhere('ef.sizeClass = :sc')->setParameter('sc', $sizeClass);
        }

        $results = $qb->getQuery()->getResult();

        if (empty($results) && ($vehicleType !== null || $sizeClass !== null)) {
            return $this->findBestMatch($transportMode);
        }

        return $results[0] ?? null;
    }

    public function save(EmissionFactor $ef): void
    {
        $this->getEntityManager()->persist($ef);
        $this->getEntityManager()->flush();
    }

    public function remove(EmissionFactor $ef): void
    {
        $this->getEntityManager()->remove($ef);
        $this->getEntityManager()->flush();
    }
}
