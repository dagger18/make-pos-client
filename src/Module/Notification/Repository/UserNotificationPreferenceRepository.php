<?php
namespace App\Module\Notification\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Core\Entity\User;
use App\Module\Core\Entity\UserNotificationPreference;

class UserNotificationPreferenceRepository extends BaseRepository
{
    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }

    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function savePreference(UserNotificationPreference $entity): UserNotificationPreference
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }
}
