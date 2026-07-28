<?php
namespace App\Module\Carrier\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Carrier\Entity\TrackingEventRaw;

class TrackingEventRawRepository extends BaseRepository
{
    /** @return TrackingEventRaw[] */
    public function findByTrackingRequest(int $requestId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.trackingRequest = :rid')
            ->setParameter('rid', $requestId)
            ->orderBy('e.receivedAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }
}
