<?php

namespace App\Module\Quote\Controller;

use App\Module\Quote\Enum\QuoteStatus;
use App\Module\Finance\Enum\CreditStatus;

use App\Module\Core\Controller\CrudController;

use App\Module\Quote\Entity\Quote;
use App\Module\Core\Entity\User;
use App\Misc\Attribute\Log;
use App\Misc\Exception\AccessDeniedException;
use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PdfActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Resolver\CrudEntityValueResolver;
use App\Resolver\CrudRequestPayloadValueResolver;
use App\Misc\Enum\CapacityType;
use App\Module\Core\Service\BaseService;
use App\Module\Core\Service\CapacityService;
use App\Module\Finance\Service\ExchangeRateGroupService;
use App\Module\Quote\Service\QuoteService;
use App\Module\Core\Service\UserService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/quote')]
#[IsGranted('ROLE_USER')]
#[AppModule('quote')]
class QuoteController extends CrudController
{
    use GetActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;
    use PdfActionTrait;

    public function __construct(
        protected BaseService $baseService,
        protected QuoteService $quoteService,
        protected UserService $userService,
        protected ExchangeRateGroupService $exchangeRateGroupService,
    ) {}

    #[Route('', methods: ['GET'])]
    #[IsGranted('GET', 'request')]
    public function LIST(Request $request): JsonResponse {
        $requestData = $request->query->all();
        $extraFilter = null;

        if (!$this->userService->canUser('SEEALL', 'Quote')) {
            $user = $this->getUser();
            $extraFilter = function ($qb) use ($user) {
                $qb->andWhere('Quote.createdBy = :owner')
                   ->setParameter('owner', $user);
            };
        }

        return $this->json($this->repository->getList($requestData, 'list', $extraFilter));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('POST', 'entity')]
    #[Log]
    public function POST(
        #[MapRequestPayload(resolver: CrudRequestPayloadValueResolver::class)]
        $entity,
        #[CurrentUser] User $user,
        Request $request,
        CapacityService $capacityService,
    ): JsonResponse {
        $capacityService->assertAllowed(CapacityType::MaxQuotes);
        if ($request->request->has('makeShipment')) {
            $capacityService->assertAllowed(CapacityType::MaxShipments);
        }
        if (method_exists($entity, 'setCreatedBy')) {
            $entity->setCreatedBy($user);
        }
        $entity->setStatus(QuoteStatus::Draft);
        $this->quoteService->setCode($entity);
        foreach ($entity->getPrices() as $price) {
            $price->setCreatedBy($user);
        }
        if (empty($entity->getExchangeRates()) || count($entity->getExchangeRates()) === 0) {
            $entity->setExchangeRates($this->exchangeRateGroupService->getCurrentExchangeRateAsMap());
        }
        if (empty($entity->getOriginDoor()) && !empty($entity->getOriginPort())) {
            $entity->setOriginDoor($entity->getOriginPort()->getDisplayName());
        }
        if (empty($entity->getDestinationDoor()) && !empty($entity->getDestinationPort())) {
            $entity->setDestinationDoor($entity->getDestinationPort()->getDisplayName());
        }
        $client = $entity->getClient();
        if ($client !== null) {
            $blockedStatuses = [CreditStatus::Blocked, CreditStatus::Blacklisted];
            if (in_array($client->getCreditStatus(), $blockedStatuses)) {
                return $this->json([
                    'error' => $this->trans('Client credit status does not allow new quotes'),
                    'creditStatus' => $client->getCreditStatus()->value,
                    'creditHoldReason' => $client->getCreditHoldReason(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
        if ($request->request->has('makeShipment')) {
            if (!$this->userService->canUser('Add', 'Shipment')) {
                throw new AccessDeniedException(AccessDeniedException::INVALID_PERMISSION);
            }
            $this->repository->save($entity, $request);
            return $this->json($this->quoteService->makeShipment($entity), Response::HTTP_CREATED);
        }
        return $this->json($this->repository->save($entity, $request), Response::HTTP_CREATED);
    }

    #[Route('/make-shipment/{id}', methods: ['POST'])]
    #[IsGranted('GET', 'entity')]
    #[Log]
    public function makeShipment(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        Quote $entity,
        int $id,
        CapacityService $capacityService,
    ): JsonResponse {
        if (!$entity || $entity->getStatus() === QuoteStatus::Booked) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        if (!$this->userService->canUser('POST', 'Shipment')) {
            throw new AccessDeniedException(AccessDeniedException::INVALID_PERMISSION);
        }
        $capacityService->assertAllowed(CapacityType::MaxShipments);
        return $this->json($this->quoteService->makeShipment($entity), Response::HTTP_CREATED);
    }
}
