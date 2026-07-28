<?php

namespace App\Module\Operations\Controller;

use App\Module\Operations\Entity\Shipment;
use App\Module\Operations\Entity\ShipmentNote;
use App\Module\Operations\Enum\NoteType;
use App\Module\Operations\Enum\NoteVisibility;
use App\Module\Operations\Repository\ShipmentNoteRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Resolver\CrudEntityValueResolver;
use App\Module\Core\Service\UserService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/notes')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class ShipmentNoteController extends AbstractController
{
    public function __construct(
        private readonly ShipmentNoteRepository $noteRepository,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly UserService $userService,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        $notes = $this->noteRepository->findByShipment($shipmentId);
        return $this->json(array_map(fn($n) => $this->serialize($n), $notes));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) {
            throw $this->createNotFoundException();
        }
        $body = json_decode($request->getContent(), true) ?? [];
        $note = new ShipmentNote();
        $note->setShipment($shipment)
             ->setBody(trim($body['body'] ?? ''))
             ->setNoteType(NoteType::tryFrom($body['noteType'] ?? '') ?? NoteType::Internal)
             ->setVisibleTo(NoteVisibility::tryFrom($body['visibleTo'] ?? '') ?? NoteVisibility::Internal)
             ->setIsPinned((bool) ($body['isPinned'] ?? false))
             ->setCreatedBy($this->getUser());

        if ($note->getBody() === '') {
            return $this->json(['error' => $this->trans('Note body cannot be empty.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->noteRepository->save($note);
        return $this->json($this->serialize($note), Response::HTTP_CREATED);
    }

    #[Route('/{noteId}', methods: ['PATCH'])]
    public function update(int $shipmentId, int $noteId, Request $request): JsonResponse
    {
        $note = $this->noteRepository->find($noteId);
        if (!$note || $note->getShipment()->getId() !== $shipmentId) {
            throw $this->createNotFoundException();
        }
        $body = json_decode($request->getContent(), true) ?? [];
        if (isset($body['body'])) {
            $note->setBody(trim($body['body']));
        }
        if (isset($body['isPinned'])) {
            $note->setIsPinned((bool) $body['isPinned']);
        }
        $this->noteRepository->save($note);
        return $this->json($this->serialize($note));
    }

    #[Route('/{noteId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $noteId): JsonResponse
    {
        $note = $this->noteRepository->find($noteId);
        if (!$note || $note->getShipment()->getId() !== $shipmentId) {
            throw $this->createNotFoundException();
        }
        if ($note->getNoteType() === NoteType::System) {
            return $this->json(['error' => $this->trans('System notes cannot be deleted.')], Response::HTTP_FORBIDDEN);
        }
        $this->noteRepository->delete($note);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function serialize(ShipmentNote $note): array
    {
        return [
            'id'        => $note->getId(),
            'noteType'  => $note->getNoteType()->value,
            'body'      => $note->getBody(),
            'isPinned'  => $note->isPinned(),
            'visibleTo' => $note->getVisibleTo()->value,
            'createdBy' => $note->getCreatedBy() ? [
                'id'        => $note->getCreatedBy()->getId(),
                'firstName' => $note->getCreatedBy()->getFirstName(),
                'lastName'  => $note->getCreatedBy()->getLastName(),
            ] : null,
            'createdAt' => $note->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
