<?php

namespace App\Module\Operations\Controller;

use App\Module\Operations\Entity\StuffingInstruction;
use App\Module\Operations\Entity\StuffingInstructionLine;
use App\Module\Operations\Repository\StuffingInstructionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/consolidation/{consolId}/stuffing')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class StuffingController extends AbstractController
{
    public function __construct(
        private readonly StuffingInstructionRepository $stuffingRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $consolId): JsonResponse
    {
        return $this->json(array_map(
            fn($s) => $this->serialize($s),
            $this->stuffingRepository->findByConsol($consolId)
        ));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(int $consolId, int $id): JsonResponse
    {
        $instruction = $this->stuffingRepository->find($id);
        if (!$instruction || $instruction->getConsolId() !== $consolId) throw $this->createNotFoundException();
        return $this->json($this->serialize($instruction));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $consolId, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $instruction = $this->hydrateInstruction(new StuffingInstruction(), $body);
        $instruction->setConsolId($consolId);

        $errors = $this->validateInstruction($instruction);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        foreach ($body['lines'] ?? [] as $lineData) {
            $line = $this->hydrateLine(new StuffingInstructionLine(), $lineData);
            $line->setStuffingInstruction($instruction);
        }

        $this->stuffingRepository->save($instruction);
        return $this->json($this->serialize($instruction), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $consolId, int $id, Request $request): JsonResponse
    {
        $instruction = $this->stuffingRepository->find($id);
        if (!$instruction || $instruction->getConsolId() !== $consolId) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrateInstruction($instruction, $body);

        $errors = $this->validateInstruction($instruction);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->stuffingRepository->save($instruction);
        return $this->json($this->serialize($instruction));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $consolId, int $id): JsonResponse
    {
        $instruction = $this->stuffingRepository->find($id);
        if (!$instruction || $instruction->getConsolId() !== $consolId) throw $this->createNotFoundException();

        $this->stuffingRepository->delete($instruction);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrateInstruction(StuffingInstruction $s, array $body): StuffingInstruction
    {
        if (isset($body['facilityId']))          $s->setFacilityId((int) $body['facilityId']);
        if (isset($body['instructionNumber']))   $s->setInstructionNumber($body['instructionNumber']);
        if (array_key_exists('containerNumber', $body)) $s->setContainerNumber($body['containerNumber'] ?: null);
        if (isset($body['status']))              $s->setStatus($body['status']);
        if (array_key_exists('scheduledAt', $body))  $s->setScheduledAt($body['scheduledAt'] ? new \DateTime($body['scheduledAt']) : null);
        if (array_key_exists('startedAt', $body))    $s->setStartedAt($body['startedAt'] ? new \DateTime($body['startedAt']) : null);
        if (array_key_exists('completedAt', $body))  $s->setCompletedAt($body['completedAt'] ? new \DateTime($body['completedAt']) : null);
        if (array_key_exists('forkliftOperator', $body)) $s->setForkliftOperator($body['forkliftOperator'] ?: null);
        if (array_key_exists('notes', $body))    $s->setNotes($body['notes'] ?: null);
        return $s;
    }

    private function hydrateLine(StuffingInstructionLine $l, array $data): StuffingInstructionLine
    {
        if (array_key_exists('receiptId', $data))  $l->setReceiptId($data['receiptId'] !== null ? (int) $data['receiptId'] : null);
        if (isset($data['shipmentId']))             $l->setShipmentId((int) $data['shipmentId']);
        if (isset($data['piecesToStuff']))          $l->setPiecesToStuff((int) $data['piecesToStuff']);
        if (isset($data['weightKg']))               $l->setWeightKg((string) $data['weightKg']);
        if (array_key_exists('volumeCbm', $data))  $l->setVolumeCbm($data['volumeCbm'] !== null ? (string) $data['volumeCbm'] : null);
        if (array_key_exists('loadSequence', $data)) $l->setLoadSequence($data['loadSequence'] !== null ? (int) $data['loadSequence'] : null);
        if (isset($data['isStuffed']))              $l->setIsStuffed((bool) $data['isStuffed']);
        if (array_key_exists('stuffedAt', $data))  $l->setStuffedAt($data['stuffedAt'] ? new \DateTime($data['stuffedAt']) : null);
        return $l;
    }

    private function validateInstruction(StuffingInstruction $s): array
    {
        $errors = [];
        if ($s->getFacilityId() === 0) $errors[] = 'Facility is required.';
        if ($s->getInstructionNumber() === '') $errors[] = 'Instruction number is required.';
        return $errors;
    }

    private function serialize(StuffingInstruction $s): array
    {
        return [
            'id'                => $s->getId(),
            'consolId'          => $s->getConsolId(),
            'facilityId'        => $s->getFacilityId(),
            'instructionNumber' => $s->getInstructionNumber(),
            'containerNumber'   => $s->getContainerNumber(),
            'status'            => $s->getStatus(),
            'scheduledAt'       => $s->getScheduledAt()?->format(\DateTimeInterface::ATOM),
            'startedAt'         => $s->getStartedAt()?->format(\DateTimeInterface::ATOM),
            'completedAt'       => $s->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'forkliftOperator'  => $s->getForkliftOperator(),
            'notes'             => $s->getNotes(),
            'createdAt'         => $s->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'lines'             => array_map(fn($l) => $this->serializeLine($l), $s->getLines()->toArray()),
        ];
    }

    private function serializeLine(StuffingInstructionLine $l): array
    {
        return [
            'id'            => $l->getId(),
            'receiptId'     => $l->getReceiptId(),
            'shipmentId'    => $l->getShipmentId(),
            'piecesToStuff' => $l->getPiecesToStuff(),
            'weightKg'      => $l->getWeightKg(),
            'volumeCbm'     => $l->getVolumeCbm(),
            'loadSequence'  => $l->getLoadSequence(),
            'isStuffed'     => $l->isStuffed(),
            'stuffedAt'     => $l->getStuffedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
