<?php
namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Repository\UserAgentRepository;
/**
 * Class UserAgentService
 * @package App\Service
 */
class UserAgentService extends BaseService
{
    /**
     * UserAgentService constructor.
     * @param UserAgentRepository $repository
     */
    public function __construct(
        protected BaseService $baseService,
        protected UserAgentRepository $repository
    ) {
        $this->reflectFromParent($baseService);
    }
}
