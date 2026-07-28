<?php
namespace App\Module\Integration\Controller;

use App\Module\Core\Entity\Port;

use App\Module\Operations\Repository\ShipmentDocumentRepository;
use App\Module\Integration\Service\PortalDocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/portal/documents')]
#[AppModule('integration')]
class PortalDocumentController extends AbstractController
{
    public function __construct(
        private readonly PortalDocumentService      $service,
        private readonly ShipmentDocumentRepository $documentRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    #[IsGranted('ROLE_PORTAL_USER')]
    public function list(#[CurrentUser] $user): JsonResponse
    {
        /** @var PortalUser $user */
        $docs = $this->service->getDocumentsForClient($user->getClient());

        return $this->json(array_map(fn($d) => [
            'id'           => $d->getId(),
            'type'         => $d->getDocType()->value,
            'typeLabel'    => $d->getDocType()->label(),
            'shipmentId'   => $d->getShipment()?->getId(),
            'shipmentCode' => $d->getShipment()?->getCode(),
            'issueDate'    => $d->getIssueDate()?->format('Y-m-d'),
        ], $docs));
    }

    #[Route('/{id}/download-url', methods: ['GET'])]
    #[IsGranted('ROLE_PORTAL_USER')]
    public function downloadUrl(#[CurrentUser] $user, int $id): JsonResponse
    {
        /** @var PortalUser $user */
        $doc = $this->documentRepository->find($id);
        if (!$doc || !$doc->isCustomerAccessible()) {
            throw $this->createNotFoundException();
        }

        $url = $this->service->generateDownloadUrl($id);

        return $this->json(['url' => $url, 'expiresIn' => 900]);
    }

    #[Route('/{id}/file', methods: ['GET'])]
    public function serveFile(int $id, Request $request): Response
    {
        $expires = (int) $request->query->get('expires', 0);
        $sig     = (string) $request->query->get('sig', '');

        if (!$this->service->validateDownloadSignature($id, $expires, $sig)) {
            return new JsonResponse(['error' => $this->trans('Invalid or expired link.')], Response::HTTP_FORBIDDEN);
        }

        $doc = $this->documentRepository->find($id);
        if (!$doc || !$doc->isCustomerAccessible()) {
            throw $this->createNotFoundException();
        }

        $media = $doc->getMedia();
        if (!$media) {
            throw $this->createNotFoundException();
        }

        return $this->redirect($media->getPath());
    }
}
