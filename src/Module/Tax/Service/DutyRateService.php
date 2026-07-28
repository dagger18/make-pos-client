<?php
namespace App\Module\Tax\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Tax\Repository\DutyRateRepository;

class DutyRateService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public DutyRateRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
