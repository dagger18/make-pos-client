<?php
namespace App\Module\Tax\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Tax\Repository\HsVersionMappingRepository;

class HsVersionMappingService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public HsVersionMappingRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
