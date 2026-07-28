<?php
namespace App\Module\Finance\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Finance\Repository\PaymentMethodRepository;

class PaymentMethodService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public PaymentMethodRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
