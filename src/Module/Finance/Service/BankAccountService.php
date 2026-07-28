<?php
namespace App\Module\Finance\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Finance\Repository\BankAccountRepository;

class BankAccountService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public BankAccountRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
