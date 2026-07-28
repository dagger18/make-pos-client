<?php
namespace App\Module\Crm\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Crm\Entity\Client;
use App\Module\Crm\Repository\ClientRepository;
/**
 * Class ClientService
 * @package App\Service
 */
class ClientService extends BaseService
{
    /**
     * ClientService constructor.
     * @param ClientRepository $repository
     */
    public function __construct(
        protected BaseService $baseService,
        public ClientRepository $repository
    ) {
        $this->reflectFromParent($baseService);
    }
}
