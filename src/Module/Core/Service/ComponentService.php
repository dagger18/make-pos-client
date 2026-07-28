<?php
namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Repository\ComponentRepository;

class ComponentService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public ComponentRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
