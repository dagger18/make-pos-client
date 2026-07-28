<?php
namespace App\Module\Operations\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Operations\Repository\ArchiveRepository;
/**
 * Class ArchiveService
 * @package App\Service
 */
class ArchiveService extends BaseService
{
    /**
     * ArchiveService constructor.
     * @param BankAccountRepository $repository
     */
    public function __construct(
        protected BaseService $baseService,
        public ArchiveRepository $repository
    ) {
        $this->reflectFromParent($baseService);
    }

}
