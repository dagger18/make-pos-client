<?php
namespace App\Module\Integration\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Integration\Entity\PortalQuoteRequest;
use App\Module\Integration\Entity\PortalUser;

class PortalQuoteRequestRepository extends BaseRepository
{
    /** @return PortalQuoteRequest[] */
    public function findByPortalUser(PortalUser $user): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.portalUser = :user')
            ->setParameter('user', $user)
            ->orderBy('q.createdDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null): PortalQuoteRequest
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }
}
