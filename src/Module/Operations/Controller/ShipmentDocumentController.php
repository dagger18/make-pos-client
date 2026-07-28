<?php

namespace App\Module\Operations\Controller;

use App\Module\Operations\Entity\ShipmentDocument;
use App\Module\Operations\Enum\DocType;
use App\Module\Core\Repository\MediaRepository;
use App\Module\Operations\Repository\ShipmentDocumentRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/documents2')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class ShipmentDocumentController extends AbstractController
{
    public function __construct(
        private readonly ShipmentDocumentRepository $docRepository,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly MediaRepository $mediaRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        return $this->json(array_map(
            fn($d) => $this->serialize($d),
            $this->docRepository->findByShipment($shipmentId)
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $docType = DocType::tryFrom($body['docType'] ?? '');
        if (!$docType) {
            return $this->json(['error' => $this->trans('Invalid doc type.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $doc = new ShipmentDocument();
        $doc->setShipment($shipment)->setDocType($docType);
        $this->hydrate($doc, $body);
        $this->docRepository->save($doc);
        return $this->json($this->serialize($doc), Response::HTTP_CREATED);
    }

    #[Route('/{docId}', methods: ['PATCH'])]
    public function update(int $shipmentId, int $docId, Request $request): JsonResponse
    {
        $doc = $this->docRepository->find($docId);
        if (!$doc || $doc->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($doc, $body);

        if ($doc->isReceived() && $doc->getReceivedAt() === null) {
            $doc->setReceivedAt(new \DateTime());
        }
        $this->docRepository->save($doc);
        return $this->json($this->serialize($doc));
    }

    #[Route('/{docId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $docId): JsonResponse
    {
        $doc = $this->docRepository->find($docId);
        if (!$doc || $doc->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();
        if ($doc->isRequired()) {
            return $this->json(['error' => $this->trans('Required documents cannot be deleted.')], Response::HTTP_FORBIDDEN);
        }
        $this->docRepository->delete($doc);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(ShipmentDocument $doc, array $body): void
    {
        if (isset($body['docReference']))  $doc->setDocReference($body['docReference'] ?: null);
        if (isset($body['isRequired']))    $doc->setIsRequired((bool) $body['isRequired']);
        if (isset($body['isReceived']))    $doc->setIsReceived((bool) $body['isReceived']);
        if (isset($body['remarks']))       $doc->setRemarks($body['remarks'] ?: null);
        if (isset($body['issueDate']))     $doc->setIssueDate($body['issueDate'] ? new \DateTime($body['issueDate']) : null);
        if (isset($body['expiryDate']))    $doc->setExpiryDate($body['expiryDate'] ? new \DateTime($body['expiryDate']) : null);
        if (array_key_exists('mediaId', $body)) {
            $doc->setMedia($body['mediaId'] ? $this->mediaRepository->find((int) $body['mediaId']) : null);
        }
    }

    private function serialize(ShipmentDocument $doc): array
    {
        return [
            'id'           => $doc->getId(),
            'docType'      => $doc->getDocType()->value,
            'docTypeLabel' => $doc->getDocType()->label(),
            'docReference' => $doc->getDocReference(),
            'isRequired'   => $doc->isRequired(),
            'isReceived'   => $doc->isReceived(),
            'receivedAt'   => $doc->getReceivedAt()?->format(\DateTimeInterface::ATOM),
            'issueDate'    => $doc->getIssueDate()?->format('Y-m-d'),
            'expiryDate'   => $doc->getExpiryDate()?->format('Y-m-d'),
            'remarks'      => $doc->getRemarks(),
            'media'        => $doc->getMedia() ? [
                'id'   => $doc->getMedia()->getId(),
                'path' => $doc->getMedia()->getPath(),
            ] : null,
        ];
    }
}
