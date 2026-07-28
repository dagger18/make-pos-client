<?php
namespace App\Module\Quote\Repository;

use App\Module\Core\Repository\BaseRepository;

class RateRepository extends BaseRepository
{
    public function findActiveRateForLane(
        string $polCode,
        string $podCode,
        ?int $providerId,
        ?string $containerType,
        string $transportType
    ): ?object {
        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.polPort', 'pol')
            ->innerJoin('r.podPort', 'pod')
            ->where('pol.code = :polCode')
            ->andWhere('pod.code = :podCode')
            ->andWhere('r.transportType = :transportType')
            ->andWhere('r.validUntil IS NULL')
            ->setParameter('polCode', $polCode)
            ->setParameter('podCode', $podCode)
            ->setParameter('transportType', $transportType)
            ->setMaxResults(1);

        if ($providerId !== null) {
            $qb->andWhere('r.provider = :provider')->setParameter('provider', $providerId);
        } else {
            $qb->andWhere('r.provider IS NULL');
        }

        if ($containerType !== null) {
            $qb->andWhere('r.containerType = :containerType')->setParameter('containerType', $containerType);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function deleteByImportJob(int $jobId): void
    {
        $this->createQueryBuilder('r')
            ->delete()
            ->where('r.importJob = :jobId')
            ->setParameter('jobId', $jobId)
            ->getQuery()
            ->execute();
    }
}
