<?php

namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;
use App\Module\Core\Repository\LocationRepository;

class LocationService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public LocationRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
