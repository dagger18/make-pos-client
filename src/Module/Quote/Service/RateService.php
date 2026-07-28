<?php
namespace App\Module\Quote\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Quote\Repository\RateRepository;

class RateService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public RateRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
