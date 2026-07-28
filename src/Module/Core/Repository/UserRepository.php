<?php

namespace App\Module\Core\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Core\Enum\Magnum;
use App\Module\Core\Enum\Permission;
use App\Module\Core\Enum\UserStatus;

class UserRepository extends BaseRepository
{
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.id != :sysAdmin')
            ->setParameter('sysAdmin', Magnum::SYSTEM_ADMIN_ID)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getUserCount($userGroupId) {
        return count(
            $this->getAll(
                [
                    'filter_userGroup' => $userGroupId, 
                    'except_id' => Magnum::SYSTEM_ADMIN_ID
                ], 
                'ENTITY'
            )
        );
    }
    public function getClientCount($userId) {
        return $this->getConnection()->fetchOne('Select count(user_id) from client_user where user_id = ' . $userId);
    }
    public function getUsersWithPermission($permission) {
        $queryBuilder = $this->createQueryBuilder('User');
        $orX = $queryBuilder->expr()->orX();
        $orX->add(
            $queryBuilder->expr()->eq('userGroup.permissions', Permission::Admin->value)
        );
        $orX->add(
            $queryBuilder->expr()->eq('userGroup.permissions', $permission)
        );
        $orX->add(
            $queryBuilder->expr()->like('userGroup.permissions', $queryBuilder->expr()->literal($permission . ',%'))
        );
        $orX->add(
            $queryBuilder->expr()->like('userGroup.permissions', $queryBuilder->expr()->literal($permission . ',%'))
        );
        $orX->add(
            $queryBuilder->expr()->like('userGroup.permissions', $queryBuilder->expr()->literal('%,' . $permission))
        );
        $orX->add(
            $queryBuilder->expr()->like('userGroup.permissions', $queryBuilder->expr()->literal('%,' . $permission. ',%'))
        );
        $queryBuilder
            ->leftJoin('User.userGroup', 'userGroup')
            ->andWhere($queryBuilder->expr()->eq('User.status', ':status'))
            ->andWhere($queryBuilder->expr()->neq('User.id', Magnum::SYSTEM_ADMIN_ID))
            ->andWhere($orX)
            ->setParameter('status',UserStatus::Active->value)
            ;
        return $queryBuilder->getQuery()->execute();
    }
}