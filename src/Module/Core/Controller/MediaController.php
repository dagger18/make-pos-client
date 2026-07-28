<?php

namespace App\Module\Core\Controller;

use App\Module\Core\Controller\CrudController;

use App\Module\Core\Entity\Media;
use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Resolver\CrudEntityValueResolver;
use App\Module\Core\Service\MediaService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/media')]
#[IsGranted('ROLE_USER')]
#[AppModule('core')]
class MediaController extends CrudController
{
    use DeleteActionTrait;
    use PutActionTrait;

    #[Route('/presign', methods: ['POST'])]
    public function presign(Request $request, MediaService $mediaService): JsonResponse
    {
        $data = $request->request->all() ?: (json_decode($request->getContent(), true) ?? []);
        $urls = $mediaService->getUploadUrls(
            $data['filename'] ?? 'upload',
            $data['mimeType'] ?? 'application/octet-stream',
            (int) ($data['size'] ?? 0)
        );
        return $this->json($urls);
    }

    #[Route('/confirm', methods: ['POST'])]
    public function confirm(Request $request, MediaService $mediaService): JsonResponse
    {
        $data = $request->request->all() ?: (json_decode($request->getContent(), true) ?? []);
        $media = $mediaService->confirmUpload($data['confirmToken'] ?? '', $data, $request);
        return $this->json($media, Response::HTTP_CREATED);
    }

    #[Route('/{id}/download-url', methods: ['GET'])]
    public function downloadUrl(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        Media $entity,
        int $id,
        MediaService $mediaService
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        return $this->json(['token' => $mediaService->generateDownloadToken($entity)]);
    }

    #[Route('/{id}/thumbnail-url', methods: ['GET'])]
    public function thumbnailUrl(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        Media $entity,
        int $id,
        MediaService $mediaService
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        $token = $entity->isHasThumb()
            ? $mediaService->generateThumbnailDownloadToken($entity)
            : $mediaService->generateDownloadToken($entity);
        return $this->json(['token' => $token]);
    }
}
