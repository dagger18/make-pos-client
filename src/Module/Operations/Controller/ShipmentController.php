<?php

namespace App\Module\Operations\Controller;

use App\Module\Core\Enum\Permission;

use App\Module\Core\Controller\CrudController;

use App\Misc\Attribute\Log;
use App\Module\Operations\Enum\ShipmentStatus;
use App\Module\Operations\Enum\SubStatus;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Misc\Exception\AccessDeniedException;
use App\Module\Operations\Repository\ShipmentActivityRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Resolver\CrudEntityValueResolver;
use App\Misc\Enum\CapacityType;
use App\Module\Core\Service\BaseService;
use App\Module\Core\Service\CapacityService;
use App\Module\Operations\Service\ShipmentService;
use App\Module\Core\Service\UserService;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class ShipmentController extends CrudController
{
    use GetActionTrait;
    use PutActionTrait;

    public function __construct(
        protected BaseService $baseService,
        protected ShipmentService $shipmentService,
        protected UserService $userService,
        protected ShipmentActivityRepository $shipmentActivityRepository,
        protected ShipmentRepository $shipmentRepository,
        protected CacheManager $cacheManager,
    ) {}

    #[Route('', methods: ['GET'])]
    #[IsGranted('GET', 'request')]
    public function LIST(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $draft = ShipmentStatus::Draft->value;

        if ($this->userService->canUser('SEEALL', 'Shipment')) {
            $extraFilter = function ($qb) use ($user, $draft) {
                $qb->andWhere(
                    $qb->expr()->orX(
                        'Shipment.status != :draft',
                        'Shipment.createdBy = :user'
                    )
                )
                ->setParameter('draft', $draft)
                ->setParameter('user', $user);
            };
        } else {
            $extraFilter = function ($qb) use ($user, $draft) {
                $qb->andWhere(
                    $qb->expr()->orX(
                        'Shipment.createdBy = :user',
                        $qb->expr()->andX(
                            'Shipment.status != :draft',
                            'Shipment.accountManager = :user'
                        )
                    )
                )
                ->setParameter('draft', $draft)
                ->setParameter('user', $user);
            };
        }

        return $this->json($this->repository->getList($request->query->all(), 'list', $extraFilter));
    }

    #[Route('/{id}', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function DETAIL(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        int $id
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        return $this->json($entity, Response::HTTP_CREATED, [], ['groups' => ['detail', 'list', 'subList']]);
    }

    #[Route('/{id}/booking', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function getBooking(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        int $id
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        return $this->json($entity->getBooking());
    }

    #[Route('/{id}/quote', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function getQuote(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        int $id
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        return $this->json($entity->getQuote());
    }

    #[Route('/{id}/documents', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function getDocuments(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        int $id
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        return $this->json($entity->getDocuments()->toArray(), Response::HTTP_OK, [], ['groups' => ['list', 'subList']]);
    }

    #[Route('/{id}/ebitnotes', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function getEbitNotes(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        int $id
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        return $this->json($entity->getEbitNotes()->toArray(), Response::HTTP_OK, [], ['groups' => ['subList']]);
    }

    #[Route('/{id}/subscribers', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function getSubscribers(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        int $id
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        $context = ['groups' => ['subList']];
        return $this->json([
            'assignedUsers' => $entity->getAssignedUsers()->toArray(),
            'manageAllUsers' => $this->userService->repository->getUsersWithPermission(
                Permission::Shipment_SEEALL->value
            ),
        ], Response::HTTP_OK, [], $context);
    }

    #[Route('/replicate/{id}', methods: ['POST'])]
    #[IsGranted('Read', 'entity')]
    public function replicate(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        int $id,
        CapacityService $capacityService,
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        if (!$this->userService->canUser('Add', 'Shipment')) {
            throw new AccessDeniedException(AccessDeniedException::INVALID_PERMISSION);
        }
        $capacityService->assertAllowed(CapacityType::MaxShipments);
        return $this->json($this->shipmentService->replicate($entity));
    }

    #[Route('/get-activities/{id}', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function getActivities(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        int $id
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }

        $record = $this->shipmentActivityRepository->find($id);
        $activities = $record?->getActivities() ?? [];

        $userIds = array_unique(array_filter(array_column($activities, 5)));
        $users = [];
        foreach ($this->userService->repository->findBy(['id' => $userIds]) as $user) {
            $logoPath = $user->getLogo()?->getPath();
            $users[$user->getId()] = [
                'id'                => $user->getId(),
                'firstName'         => $user->getFirstName(),
                'lastName'          => $user->getLastName(),
                'logo' => [
                  'thumbnailPath' => $logoPath
                                  ? $this->cacheManager->getBrowserPath($logoPath, 'thumbnail')
                                  : null
                ]
            ];
        }

        return $this->json([
            'activities'   => $activities,
            'shipmentCode' => $entity->getCode(),
            'users'        => array_values($users),
        ]);
    }

    #[Route('/{id}/hold', methods: ['POST'])]
    #[IsGranted('PUT', 'entity')]
    public function hold(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        int $id,
        Request $request
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        $body = json_decode($request->getContent(), true) ?? [];
        $reason = trim($body['reason'] ?? '');
        if ($reason === '') {
            return $this->json(['error' => $this->trans('Hold reason is required.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $entity->setIsOnHold(true)->setHoldReason($reason);
        $this->shipmentRepository->save($entity);
        return $this->json($entity, Response::HTTP_OK, [], ['groups' => ['detail', 'list', 'subList']]);
    }

    #[Route('/{id}/unhold', methods: ['POST'])]
    #[IsGranted('PUT', 'entity')]
    public function unhold(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        int $id
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        $entity->setIsOnHold(false)->setHoldReason(null);
        $this->shipmentRepository->save($entity);
        return $this->json($entity, Response::HTTP_OK, [], ['groups' => ['detail', 'list', 'subList']]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('DELETE', 'entity')]
    #[Log]
    public function DELETE(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        int $id,
        Request $request
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        $this->shipmentService->doDeleteShipment($entity);
        return $this->json([]);
    }
}
