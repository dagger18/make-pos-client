<?php
namespace App\Module\Quote\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Quote\Repository\CalculationTypeRepository;

class CalculationTypeService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public CalculationTypeRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
