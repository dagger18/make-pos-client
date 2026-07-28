<?php
namespace App\Module\Finance\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Finance\Repository\ChargeRepository;

class ChargeService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public ChargeRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
