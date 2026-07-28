<?php
namespace App\Misc\Traits\Controller;

use App\Module\Core\Entity\User;
use App\Misc\Attribute\Log;
use App\Module\Core\Service\RequestService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Resolver\CrudRequestPayloadValueResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

/** @property \App\Repository\BaseRepository $repository */
trait PutActionTrait
{

    #[Route('/{id}', methods: ['PUT'])]
    #[IsGranted('PUT', 'entity')]
    #[Log]
    public function PUT(
        #[MapRequestPayload(
            resolver: CrudRequestPayloadValueResolver::class
        )]
        $entity,
        int $id,
        Request $request
    ): JsonResponse {
        return $this->json($this->repository->save($entity, $request), Response::HTTP_OK);
    }

}