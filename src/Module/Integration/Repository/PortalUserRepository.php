<?php
namespace App\Module\Integration\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Integration\Entity\PortalUser;

class PortalUserRepository extends BaseRepository
{
    public function findByEmail(string $email): ?PortalUser
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null): PortalUser
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }
}
