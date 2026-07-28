<?php

namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Repository\DepartmentRepository;

class DepartmentService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public DepartmentRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
