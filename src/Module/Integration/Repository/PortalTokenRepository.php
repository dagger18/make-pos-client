<?php
namespace App\Module\Integration\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Integration\Entity\PortalToken;
use App\Module\Integration\Entity\PortalUser;

class PortalTokenRepository extends BaseRepository
{
    public function findValidToken(string $token): ?PortalToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.token = :token')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', time())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null): PortalToken
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }

    public function deleteByUser(PortalUser $user): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.portalUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
