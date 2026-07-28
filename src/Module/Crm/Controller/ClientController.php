<?php

namespace App\Module\Crm\Controller;

use App\Module\Core\Controller\CrudController;

use App\Module\Crm\Entity\Client;
use App\Module\Core\Entity\User;
use App\Misc\Attribute\Log;
use App\Module\Finance\Enum\CreditStatus;
use App\Module\Finance\Repository\CreditLimitHistoryRepository;
use App\Module\Core\Service\BaseService;
use App\Module\Finance\Service\CreditCheckService;
use App\Module\Core\Service\UserService;
use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Resolver\CrudRequestPayloadValueResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client')]
#[IsGranted('ROLE_USER')]
#[AppModule('crm')]
class ClientController extends CrudController
{
    use GetActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;

    public function __construct(
        BaseService $baseService,
        protected UserService $userService,
    ) {
        parent::__construct($baseService);
    }

    #[Route('', methods: ['GET'])]
    #[IsGranted('GET', 'request')]
    public function LIST(Request $request): JsonResponse {
        $requestData = $request->query->all();
        $extraFilter = null;

        if (!$this->userService->canUser('SEEALL', 'Client')) {
            $user = $this->getUser();
            $extraFilter = function ($qb) use ($user) {
                $qb->andWhere('Client.createdBy = :owner OR :owner MEMBER OF Client.assignedUsers OR Client.accountManager = :owner')
                   ->setParameter('owner', $user);
            };
        }

        return $this->json($this->repository->getList($requestData, 'list', $extraFilter));
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

    #[Route('/{id}/credit-status', methods: ['PUT'])]
    public function updateCreditStatus(
        int $id,
        Request $request,
        CreditCheckService $creditCheckService,
        #[CurrentUser] User $user,
    ): JsonResponse {
        /** @var Client|null $client */
        $client = $this->repository->find($id);
        if (!$client) {
            throw $this->createNotFoundException();
        }

        $oldStatus = $client->getCreditStatus();
        $oldLimit  = $client->getCreditLimit();

        $newStatusValue = $request->request->getString('creditStatus');
        $newStatus      = CreditStatus::tryFrom($newStatusValue) ?? $oldStatus;

        $client->setCreditStatus($newStatus);
        $client->setCreditHoldReason($request->request->getString('creditHoldReason') ?: null);
        $client->setCreditReviewedBy($user);

        $reviewedAt = $request->request->getString('creditReviewedAt');
        if ($reviewedAt) {
            $client->setCreditReviewedAt(new \DateTime($reviewedAt));
        }

        if ($newStatus !== $oldStatus) {
            $creditCheckService->recordHistory(
                client:         $client,
                changedBy:      $user,
                changeType:     'STATUS_CHANGE',
                oldStatus:      $oldStatus,
                newStatus:      $newStatus,
                oldLimitAmount: $oldLimit?->getAmount(),
                newLimitAmount: $oldLimit?->getAmount(),
                currency:       $oldLimit?->getCurrency(),
                reason:         $client->getCreditHoldReason(),
            );
        }

        $this->repository->save($client);

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/credit-check', methods: ['GET'])]
    public function creditCheck(
        int $id,
        CreditCheckService $creditCheckService,
    ): JsonResponse {
        /** @var Client|null $client */
        $client = $this->repository->find($id);
        if (!$client) {
            throw $this->createNotFoundException('Client not found');
        }
        return $this->json($creditCheckService->check($client));
    }

    #[Route('/{id}/credit-history', methods: ['GET'])]
    public function creditHistory(
        int $id,
        CreditLimitHistoryRepository $historyRepository,
    ): JsonResponse {
        $entries = $historyRepository->findForClient($id);
        return $this->json(array_map(fn($h) => [
            'id'             => $h->getId(),
            'changeType'     => $h->getChangeType(),
            'oldStatus'      => $h->getOldStatus()?->value,
            'newStatus'      => $h->getNewStatus()?->value,
            'oldLimitAmount' => $h->getOldLimitAmount(),
            'newLimitAmount' => $h->getNewLimitAmount(),
            'currency'       => $h->getCurrency(),
            'reason'         => $h->getReason(),
            'changedBy'      => $h->getChangedBy() ? [
                'id'        => $h->getChangedBy()->getId(),
                'firstName' => $h->getChangedBy()->getFirstName(),
                'lastName'  => $h->getChangedBy()->getLastName(),
            ] : null,
            'createdDate'    => $h->getCreatedDate()?->format(\DateTimeInterface::ATOM),
        ], $entries));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('POST', 'entity')]
    #[Log]
    public function POST(
        #[MapRequestPayload(
            resolver: CrudRequestPayloadValueResolver::class
        )]
        Client $entity,
        #[CurrentUser] User $user,
        Request $request
    ): JsonResponse {
        $bankAccount = $entity->getBankAccounts()[0];
        $entity->setDefaultBankAccount($bankAccount);
        $entity->setDefaultInvoiceInfo($entity->getInvoiceInfos()[0]);
        $entity->getInvoiceInfos()[0]->setBankAccount($bankAccount);
        $entity->setDefaultContact($entity->getContacts()[0]);
        $entity->setCreatedBy($user);
        $entity->setAccountManager($user);
        return $this->json($this->repository->save($entity, $request), Response::HTTP_CREATED, []);
    }
}
