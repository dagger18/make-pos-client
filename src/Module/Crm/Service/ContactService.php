<?php
namespace App\Module\Crm\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Crm\Repository\ContactRepository;

class ContactService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public ContactRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
