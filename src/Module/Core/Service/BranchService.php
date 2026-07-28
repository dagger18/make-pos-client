<?php

namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Repository\BranchRepository;

class BranchService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public BranchRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
