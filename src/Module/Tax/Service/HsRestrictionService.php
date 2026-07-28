<?php
namespace App\Module\Tax\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Tax\Repository\HsRestrictionRepository;

class HsRestrictionService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public HsRestrictionRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
