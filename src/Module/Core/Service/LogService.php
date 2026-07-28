<?php
namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Repository\LogRepository;
/**
 * Class LogService
 * @package App\Service
 */
class LogService extends BaseService
{
    /**
     * LogService constructor.
     * @param LogRepository $repository
     */
    public function __construct(
        protected LogRepository $repository
    ) {
    }
}
