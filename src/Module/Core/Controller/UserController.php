<?php

namespace App\Module\Core\Controller;

use App\Module\Core\Controller\CrudController;

use App\Module\Core\Entity\User;
use App\Misc\Attribute\Log;
use App\Module\Core\Enum\Magnum;
use App\Module\Core\Enum\UserStatus;
use App\Misc\Exception\AccessDeniedException;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Module\Finance\Repository\ExchangeRateGroupRepository;
use App\Resolver\CrudEntityValueResolver;
use App\Resolver\CrudRequestPayloadValueResolver;
use App\Module\Core\Service\CommonService;
use App\Module\Core\Service\ConfigService;
use App\Module\Core\Service\MailService;
use App\Misc\Enum\CapacityType;
use App\Module\Core\Service\CapacityService;
use App\Module\Core\Service\MasterSyncService;
use App\Module\Core\Service\UserService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/user')]
#[IsGranted('ROLE_USER')]
#[AppModule('core')]
class UserController extends CrudController
{
    use PutActionTrait;
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse {
        // no sys admin
        $request->query->set('except_id', Magnum::SYSTEM_ADMIN_ID);
        return $this->json($this->repository->getList($request, 'list'));
    }
    
    #[Route('/invite', methods: ['POST'])]
    #[Log]
    public function invite(
        #[MapRequestPayload(
            serializationContext: ['...'],
            resolver: CrudRequestPayloadValueResolver::class
        )]
        $entity,
        UserService $userService,
        MasterSyncService $masterSyncService,
        CapacityService $capacityService,
    ): JsonResponse {
        $capacityService->assertAllowed(CapacityType::MaxUsers);
        $result = $userService->invite($entity);
        $masterSyncService->syncCurrentUsers();
        return $this->json($result, Response::HTTP_CREATED);
    }

    

    #[Route('/get-users-with-permission/{permission}', methods: ['GET'])]
    public function getUsersWithPermission(
        int $permission
    ): JsonResponse {
        return $this->json($this->repository->getUsersWithPermission($permission), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function DETAIL(
        #[MapEntity(
            resolver: CrudEntityValueResolver::class
        )]
        User $entity, int $id
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException(
                'No entity found for id '. $id
            );
        }
        return $this->json($entity, Response::HTTP_CREATED, []);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('DELETE', 'entity')]
    #[Log]
    public function DELETE(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity, int $id,
        MasterSyncService $masterSyncService,
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id '. $id);
        }
        $this->repository->delete($entity);
        $masterSyncService->syncCurrentUsers();
        return $this->json(['result' => 'success']);
    }
}