<?php
namespace App\Module\Finance\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Finance\Repository\CurrencyRepository;

class CurrencyService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public CurrencyRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
