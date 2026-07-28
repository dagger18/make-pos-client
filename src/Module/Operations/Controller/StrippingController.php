<?php

namespace App\Module\Operations\Controller;

use App\Module\Operations\Entity\StrippingInstruction;
use App\Module\Operations\Entity\StrippingResult;
use App\Module\Operations\Repository\StrippingInstructionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/consolidation/{consolId}/stripping')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class StrippingController extends AbstractController
{
    public function __construct(
        private readonly StrippingInstructionRepository $strippingRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $consolId): JsonResponse
    {
        return $this->json(array_map(
            fn($s) => $this->serialize($s),
            $this->strippingRepository->findByConsol($consolId)
        ));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(int $consolId, int $id): JsonResponse
    {
        $instruction = $this->strippingRepository->find($id);
        if (!$instruction || $instruction->getConsolId() !== $consolId) throw $this->createNotFoundException();
        return $this->json($this->serialize($instruction));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $consolId, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $instruction = $this->hydrateInstruction(new StrippingInstruction(), $body);
        $instruction->setConsolId($consolId);

        $errors = $this->validateInstruction($instruction);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        foreach ($body['results'] ?? [] as $resultData) {
            $result = $this->hydrateResult(new StrippingResult(), $resultData);
            $result->setStrippingInstruction($instruction);
        }

        $this->strippingRepository->save($instruction);
        return $this->json($this->serialize($instruction), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $consolId, int $id, Request $request): JsonResponse
    {
        $instruction = $this->strippingRepository->find($id);
        if (!$instruction || $instruction->getConsolId() !== $consolId) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrateInstruction($instruction, $body);

        $errors = $this->validateInstruction($instruction);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->strippingRepository->save($instruction);
        return $this->json($this->serialize($instruction));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $consolId, int $id): JsonResponse
    {
        $instruction = $this->strippingRepository->find($id);
        if (!$instruction || $instruction->getConsolId() !== $consolId) throw $this->createNotFoundException();

        $this->strippingRepository->delete($instruction);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrateInstruction(StrippingInstruction $s, array $body): StrippingInstruction
    {
        if (isset($body['facilityId']))         $s->setFacilityId((int) $body['facilityId']);
        if (isset($body['instructionNumber']))  $s->setInstructionNumber($body['instructionNumber']);
        if (array_key_exists('containerNumber', $body))  $s->setContainerNumber($body['containerNumber'] ?: null);
        if (array_key_exists('containerArrival', $body)) $s->setContainerArrival($body['containerArrival'] ? new \DateTime($body['containerArrival']) : null);
        if (isset($body['status']))             $s->setStatus($body['status']);
        if (array_key_exists('startedAt', $body))   $s->setStartedAt($body['startedAt'] ? new \DateTime($body['startedAt']) : null);
        if (array_key_exists('completedAt', $body)) $s->setCompletedAt($body['completedAt'] ? new \DateTime($body['completedAt']) : null);
        if (array_key_exists('notes', $body))   $s->setNotes($body['notes'] ?: null);
        return $s;
    }

    private function hydrateResult(StrippingResult $r, array $data): StrippingResult
    {
        if (isset($data['shipmentId']))           $r->setShipmentId((int) $data['shipmentId']);
        if (array_key_exists('hblNumber', $data)) $r->setHblNumber($data['hblNumber'] ?: null);
        if (isset($data['piecesStripped']))        $r->setPiecesStripped((int) $data['piecesStripped']);
        if (array_key_exists('weightKg', $data))  $r->setWeightKg($data['weightKg'] !== null ? (string) $data['weightKg'] : null);
        if (isset($data['conditionCode']))         $r->setConditionCode($data['conditionCode']);
        if (array_key_exists('damageNotes', $data))  $r->setDamageNotes($data['damageNotes'] ?: null);
        if (array_key_exists('storageLocation', $data)) $r->setStorageLocation($data['storageLocation'] ?: null);
        if (isset($data['strippedAt']))            $r->setStrippedAt(new \DateTime($data['strippedAt']));
        return $r;
    }

    private function validateInstruction(StrippingInstruction $s): array
    {
        $errors = [];
        if ($s->getFacilityId() === 0) $errors[] = 'Facility is required.';
        if ($s->getInstructionNumber() === '') $errors[] = 'Instruction number is required.';
        return $errors;
    }

    private function serialize(StrippingInstruction $s): array
    {
        return [
            'id'                => $s->getId(),
            'consolId'          => $s->getConsolId(),
            'facilityId'        => $s->getFacilityId(),
            'instructionNumber' => $s->getInstructionNumber(),
            'containerNumber'   => $s->getContainerNumber(),
            'containerArrival'  => $s->getContainerArrival()?->format(\DateTimeInterface::ATOM),
            'status'            => $s->getStatus(),
            'startedAt'         => $s->getStartedAt()?->format(\DateTimeInterface::ATOM),
            'completedAt'       => $s->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'notes'             => $s->getNotes(),
            'createdAt'         => $s->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'results'           => array_map(fn($r) => $this->serializeResult($r), $s->getResults()->toArray()),
        ];
    }

    private function serializeResult(StrippingResult $r): array
    {
        return [
            'id'              => $r->getId(),
            'shipmentId'      => $r->getShipmentId(),
            'hblNumber'       => $r->getHblNumber(),
            'piecesStripped'  => $r->getPiecesStripped(),
            'weightKg'        => $r->getWeightKg(),
            'conditionCode'   => $r->getConditionCode(),
            'damageNotes'     => $r->getDamageNotes(),
            'storageLocation' => $r->getStorageLocation(),
            'strippedAt'      => $r->getStrippedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
