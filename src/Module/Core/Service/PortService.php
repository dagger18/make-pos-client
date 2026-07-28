<?php
namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Repository\PortRepository;

class PortService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public PortRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
