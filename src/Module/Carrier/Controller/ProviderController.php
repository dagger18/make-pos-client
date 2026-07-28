<?php

namespace App\Module\Carrier\Controller;

use App\Module\Reporting\Enum\DatasetRowType;

use App\Module\Core\Controller\CrudController;

use App\Module\Finance\Entity\BankAccount;
use App\Module\Crm\Entity\Contact;
use App\Module\Finance\Entity\InvoiceInfo;
use App\Module\Carrier\Entity\Provider;
use App\Module\Core\Entity\User;
use App\Misc\Attribute\Log;
use App\Module\Core\Enum\Magnum;
use App\Module\Carrier\Enum\ProviderType;
use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Resolver\CrudRequestPayloadValueResolver;
use App\Module\Core\Service\BaseService;
use App\Module\Core\Service\ConfigService;
use App\Module\Core\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/provider')]
#[IsGranted('ROLE_USER')]
#[AppModule('carrier')]
class ProviderController extends CrudController
{
    use PutActionTrait;
    use DeleteActionTrait;

    public function __construct(
        BaseService $baseService,
        protected UserService $userService,
        protected ConfigService $configService,
    ) {
        parent::__construct($baseService);
    }

    // we are using ID 1 for company profile, so list fetching will be filtered
    #[Route('', methods: ['GET'])]
    #[IsGranted('GET', 'request')]
    public function list(Request $request): JsonResponse|StreamedResponse {
        $request->query->set('except_id', 1);
        $requestData = $request->query->all();
        $extraFilter = null;

        if (false && !$this->userService->canUser('SEEALL', 'Provider')) {
            $user = $this->getUser();
            $extraFilter = function ($qb) use ($user) {
                $qb->andWhere('Provider.createdBy = :owner OR :owner MEMBER OF Provider.assignedUsers')
                   ->setParameter('owner', $user);
            };
        }

        $data = $this->repository->getList($requestData, 'list', $extraFilter);

        if(isset($requestData['rowType'])
            //&& $requestData['rowType'] === DatasetRowType::Entity->value
            && isset($requestData['export'])
        ) {
            return $this->service->export($requestData, $data);
        }
        return $this->json($data);
    }

    #[Route('/check-duplicates', methods: ['GET'])]
    public function CHECK_DUPLICATES(Request $request): JsonResponse
    {
        $name = trim($request->query->getString('name', ''));
        $taxNumber = trim($request->query->getString('taxNumber', ''));
        if (strlen($name) < 2) {
            return $this->json([]);
        }
        return $this->json(
            $this->repository->findPotentialDuplicates($name, $taxNumber ?: null)
        );
    }

    /** @param Provider $entity */
    #[Route('', methods: ['POST'])]
    #[IsGranted('POST', 'entity')]
    #[Log]
    public function POST(
        #[MapRequestPayload(
            resolver: CrudRequestPayloadValueResolver::class
        )]
        $entity,
        #[CurrentUser] User $user,
        Request $request
    ): JsonResponse {
        $bankAccount = $entity->getBankAccounts()[0];
        $entity->setDefaultBankAccount($bankAccount);
        $entity->setDefaultInvoiceInfo($entity->getInvoiceInfos()[0]);
        $entity->getInvoiceInfos()[0]->setBankAccount($bankAccount);
        $entity->setDefaultContact($entity->getContacts()[0]);
        $entity->setCreatedBy($user);
        return $this->json($this->repository->save($entity, $request), Response::HTTP_CREATED, []);
    }

    #[Route('/{id}', methods: ['PUT'])]
    #[IsGranted('PUT', 'entity')]
    #[Log]
    public function PUT(
        #[MapRequestPayload(resolver: CrudRequestPayloadValueResolver::class)]
        $entity,
        int $id,
        Request $request
    ): JsonResponse {
        $result = $this->repository->save($entity, $request);
        if ($id === Magnum::COMPANY_PROVIDER_ID) {
            $config = $this->configService->getConfigValue('organization', isJson: true) ?? [];
            if (empty($config['providerReady'])) {
                $config['providerReady'] = true;
                $this->configService->setConfig('organization', json_encode($config));
            }
        }
        return $this->json($result, Response::HTTP_OK);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function DETAIL(int $id, #[CurrentUser] User $user): JsonResponse {
        $entity = $this->repository->find($id);

        if ($entity === null) {
            if ($id !== Magnum::COMPANY_PROVIDER_ID) {
                throw $this->createNotFoundException('No entity found for id ' . $id);
            }
            $entity = $this->createDefaultCompanyProfile($user);
        }

        return $this->json($entity, Response::HTTP_OK);
    }

    

    private function createDefaultCompanyProfile(User $user): Provider
    {
        $bankAccount = new BankAccount();
        $contact = (new Contact())->setFirstName('-');
        $invoiceInfo = (new InvoiceInfo())->setCompany('-')->setBankAccount($bankAccount);

        $entity = (new Provider())
            ->setName('My Company')
            ->setCode('MYCO')
            ->setType(ProviderType::Other)
            ->setDefaultBankAccount($bankAccount)
            ->setDefaultInvoiceInfo($invoiceInfo)
            ->setDefaultContact($contact)
            ->setCreatedBy($user);
        $entity->addBankAccount($bankAccount);
        $entity->addInvoiceInfo($invoiceInfo);
        $entity->addContact($contact);

        $this->repository->save($entity);
        return $entity;
    }
}