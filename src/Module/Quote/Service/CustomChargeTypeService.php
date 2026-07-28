<?php
namespace App\Module\Quote\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Quote\Repository\CustomChargeTypeRepository;

class CustomChargeTypeService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public CustomChargeTypeRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
