<?php

namespace App\EventListener;

use App\Module\Core\Entity\User;
use App\Module\Core\Entity\UserGroup;
use App\Module\Core\Repository\UserRepository;
use App\Module\Core\Repository\UserTokenRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsEntityListener(event: Events::postUpdate, entity: User::class, method: 'onUserUpdate')]
#[AsEntityListener(event: Events::postUpdate, entity: UserGroup::class, method: 'onUserGroupUpdate')]
class UserTokenUpdateListener
{
    public function __construct(
        private TagAwareCacheInterface $appCache,
        private UserTokenRepository $userTokenRepository,
        private UserRepository $userRepository,
    ) {}

    public function onUserUpdate(User $entity, PostUpdateEventArgs $args): void
    {
        /** @var EntityManagerInterface $em */
        $em = $args->getObjectManager();
        $changeSet = $em->getUnitOfWork()->getEntityChangeSet($entity);

        if (isset($changeSet['userGroup']) || isset($changeSet['status'])) {
            $userTokens = $this->userTokenRepository->findBy(['user' => $entity]);
            foreach ($userTokens as $userToken) {
                $this->userTokenRepository->delete($userToken);
            }
        }
    }

    public function onUserGroupUpdate(UserGroup $entity, PostUpdateEventArgs $args): void
    {
        /** @var EntityManagerInterface $em */
        $em = $args->getObjectManager();
        $changeSet = $em->getUnitOfWork()->getEntityChangeSet($entity);

        if (isset($changeSet['name']) || isset($changeSet['permissions'])) {
            $users = $this->userRepository->findBy(['userGroup' => $entity]);
            foreach ($users as $user) {
                $userTokens = $this->userTokenRepository->findBy(['user' => $user]);
                foreach ($userTokens as $userToken) {
                    $this->userTokenRepository->delete($userToken);
                }
            }
        }
    }
}
