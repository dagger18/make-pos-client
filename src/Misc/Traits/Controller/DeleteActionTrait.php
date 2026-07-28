<?php
namespace App\Misc\Traits\Controller;

use App\Module\Core\Entity\User;
use App\Misc\Attribute\Log;
use App\Module\Core\Service\RequestService;
use App\Resolver\CrudEntityValueResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Resolver\CrudRequestPayloadValueResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

/** @property \App\Repository\BaseRepository $repository */
trait DeleteActionTrait
{

    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('DELETE', 'entity')]
    #[Log]
    public function DELETE(
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
        $this->repository->delete($entity);
        return $this->json(['result' => 'success']);
    }

}