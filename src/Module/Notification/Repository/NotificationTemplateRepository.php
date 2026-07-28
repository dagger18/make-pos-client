<?php
namespace App\Module\Notification\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Notification\Entity\NotificationTemplate;

class NotificationTemplateRepository extends BaseRepository
{
    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }
}
