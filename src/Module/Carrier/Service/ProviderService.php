<?php
namespace App\Module\Carrier\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Crm\Entity\Contact;
use App\Module\Finance\Entity\BankAccount;
use App\Module\Finance\Entity\InvoiceInfo;
use App\Module\Carrier\Repository\ProviderRepository;
/**
 * Class ProviderService
 * @package App\Service
 */
class ProviderService extends BaseService
{
    /**
     * ProviderService constructor.
     * @param ProviderRepository $repository
     */
    public function __construct(
        protected BaseService $baseService,
        public ProviderRepository $repository
    ) {
        $this->reflectFromParent($baseService);
    }

    
    public function addInvoiceInfo(InvoiceInfo $entity, int $providerId) {
        $provider = $this->repository->find($providerId);
        $provider->addInvoiceInfo($entity);
        $this->repository->save($provider);
        return $entity;
    }

    public function addContact(Contact $entity, int $providerId) {
        $provider = $this->repository->find($providerId);
        $provider->addContact($entity);
        $this->repository->save($provider);
        return $entity;
    }
    
    public function addBankAccount(BankAccount $entity, int $providerId) {
        $provider = $this->repository->find($providerId);
        $provider->addBankAccount($entity);
        $this->repository->save($provider);
        return $entity;
    }
}
