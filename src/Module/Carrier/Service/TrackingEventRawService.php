<?php
namespace App\Module\Carrier\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Carrier\Repository\TrackingEventRawRepository;

class TrackingEventRawService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public TrackingEventRawRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
