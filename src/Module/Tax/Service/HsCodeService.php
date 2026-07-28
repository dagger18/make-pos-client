<?php
namespace App\Module\Tax\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Tax\Repository\HsCodeRepository;

class HsCodeService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public HsCodeRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
