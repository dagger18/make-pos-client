<?php
namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Repository\UserGroupRepository;
/**
 * Class UserGroupService
 * @package App\Service
 */
class UserGroupService extends BaseService
{
    /**
     * UserGroupService constructor.
     * @param UserGroupRepository $repository
     */
    public function __construct(
        protected BaseService $baseService,
        public UserGroupRepository $repository
    ) {
        $this->reflectFromParent($baseService);
    }
}
