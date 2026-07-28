<?php
namespace App\Module\Carrier\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Carrier\Repository\CarrierEventMappingRepository;

class CarrierEventMappingService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public CarrierEventMappingRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
