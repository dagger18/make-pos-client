<?php
namespace App\Misc\Traits\Controller;

use App\Resolver\CrudEntityValueResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** @property \App\Repository\BaseRepository $repository */
trait GetActionTrait
{

    #[Route('', methods: ['GET'])]
    #[IsGranted('GET', 'request')]
    public function LIST(Request $request): JsonResponse {
        $requestData = $request->query->all();
        $data = $this->repository->getList($requestData, 'list');
        return $this->json($data);
    }

    #[Route('/{id}', methods: ['GET'])]
    #[IsGranted('GET', 'entity')]
    public function DETAIL(
        #[MapEntity(
            resolver: CrudEntityValueResolver::class
        )]
        $entity, int $id
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException(
                'No entity found for id '. $id
            );
        }
        return $this->json($entity, Response::HTTP_CREATED, []);
    }

}