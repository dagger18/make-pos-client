<?php
namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Repository\PackageTypeRepository;

class PackageTypeService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public PackageTypeRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
