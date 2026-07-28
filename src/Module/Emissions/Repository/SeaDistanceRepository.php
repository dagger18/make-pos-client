<?php
declare(strict_types=1);
namespace App\Module\Emissions\Repository;

use App\Module\Emissions\Entity\SeaDistance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SeaDistanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeaDistance::class);
    }

    public function findDistance(string $polCode, string $podCode): ?SeaDistance
    {
        return $this->findOneBy([
            'polCode' => strtoupper($polCode),
            'podCode' => strtoupper($podCode),
        ]);
    }

    public function upsert(SeaDistance $sd): void
    {
        $existing = $this->findDistance($sd->getPolCode(), $sd->getPodCode());
        if ($existing) {
            $existing->setDistanceKm($sd->getDistanceKm());
            $existing->setViaCanal($sd->getViaCanal());
            $existing->setSource($sd->getSource());
            $existing->setUpdatedAt($sd->getUpdatedAt() ?? new \DateTime());
            $this->getEntityManager()->flush();
            return;
        }
        $this->getEntityManager()->persist($sd);
        $this->getEntityManager()->flush();
    }

    public function save(SeaDistance $sd): void
    {
        $this->getEntityManager()->persist($sd);
        $this->getEntityManager()->flush();
    }

    public function remove(SeaDistance $sd): void
    {
        $this->getEntityManager()->remove($sd);
        $this->getEntityManager()->flush();
    }
}
