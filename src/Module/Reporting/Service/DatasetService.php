<?php
namespace App\Module\Reporting\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Reporting\Repository\DatasetRepository;

class DatasetService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public DatasetRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
