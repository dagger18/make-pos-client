<?php
namespace App\Module\Finance\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Finance\Repository\ChargeItemRepository;

class ChargeItemService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public ChargeItemRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
