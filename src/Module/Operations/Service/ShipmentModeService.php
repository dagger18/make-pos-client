<?php
namespace App\Module\Operations\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Operations\Repository\ShipmentModeRepository;

class ShipmentModeService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public ShipmentModeRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
