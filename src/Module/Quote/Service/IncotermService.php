<?php
namespace App\Module\Quote\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Quote\Repository\IncotermRepository;

class IncotermService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public IncotermRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
