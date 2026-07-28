<?php
namespace App\Module\Quote\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Quote\Repository\QuotePriceRepository;
use App\Module\Quote\Repository\CalculationTypeRepository;

class QuotePriceService extends BaseService
{

    public function __construct(
        protected BaseService $baseService,
        public QuotePriceRepository $repository
    ) {
        $this->reflectFromParent($baseService);
    }

}
