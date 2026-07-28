<?php
namespace App\Module\Quote\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Quote\Repository\PriceMarkupRepository;

class PriceMarkupService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public PriceMarkupRepository $repository
    ) {
        $this->reflectFromParent($baseService);
    }
}
