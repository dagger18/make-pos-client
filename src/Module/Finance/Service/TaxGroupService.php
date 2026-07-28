<?php
namespace App\Module\Finance\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Finance\Repository\TaxGroupRepository;

class TaxGroupService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public TaxGroupRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
