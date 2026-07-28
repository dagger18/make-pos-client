<?php
namespace App\Module\Finance\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Finance\Repository\InvoiceInfoRepository;

class InvoiceInfoService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public InvoiceInfoRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
