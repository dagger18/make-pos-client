<?php

namespace App\Module\Operations\Controller;

use App\Module\Operations\Entity\ShipmentMilestone;
use App\Module\Carrier\Enum\MilestoneCode;
use App\Module\Operations\Repository\ShipmentMilestoneRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Operations\Repository\ShipmentTaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/milestones')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class ShipmentMilestoneController extends AbstractController
{
    public function __construct(
        private readonly ShipmentMilestoneRepository $milestoneRepository,
        private readonly ShipmentRepository          $shipmentRepository,
        private readonly ShipmentTaskRepository      $taskRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        return $this->json(array_map(
            fn($m) => $this->serialize($m),
            $this->milestoneRepository->findByShipment($shipmentId)
        ));
    }

    #[Route('', methods: ['POST'])]
    public function upsert(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $code = MilestoneCode::tryFrom($body['milestoneCode'] ?? '');
        if (!$code) {
            return $this->json(['error' => $this->trans('Invalid milestoneCode.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $milestone = $this->milestoneRepository->findByShipmentAndCode($shipmentId, $code)
            ?? (new ShipmentMilestone())->setShipment($shipment)->setMilestoneCode($code);

        if (!empty($body['actualDate']) && $milestone->getActualDate() === null) {
            $blocked = $this->taskRepository->findBlockingTasks($shipmentId, $code->value);
            if (!empty($blocked)) {
                return $this->json([
                    'error'         => $this->trans('Milestone blocked by incomplete mandatory tasks.'),
                    'blockingTasks' => array_map(fn($t) => ['id' => $t->getId(), 'title' => $t->getTitle()], $blocked),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $isNew = $milestone->getId() === null;
        $this->hydrateFromBody($milestone, $body);
        $this->milestoneRepository->save($milestone);

        return $this->json($this->serialize($milestone), $isNew ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    #[Route('/{milestoneId}', methods: ['PATCH'])]
    public function update(int $shipmentId, int $milestoneId, Request $request): JsonResponse
    {
        $milestone = $this->milestoneRepository->find($milestoneId);
        if (!$milestone || $milestone->getShipment()->getId() !== $shipmentId) {
            throw $this->createNotFoundException();
        }

        $body = json_decode($request->getContent(), true) ?? [];

        if (!empty($body['actualDate']) && $milestone->getActualDate() === null) {
            $blocked = $this->taskRepository->findBlockingTasks($shipmentId, $milestone->getMilestoneCode()->value);
            if (!empty($blocked)) {
                return $this->json([
                    'error'         => $this->trans('Milestone blocked by incomplete mandatory tasks.'),
                    'blockingTasks' => array_map(fn($t) => ['id' => $t->getId(), 'title' => $t->getTitle()], $blocked),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $this->hydrateFromBody($milestone, $body);
        $this->milestoneRepository->save($milestone);

        return $this->json($this->serialize($milestone));
    }

    #[Route('/{milestoneId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $milestoneId): JsonResponse
    {
        $milestone = $this->milestoneRepository->find($milestoneId);
        if (!$milestone || $milestone->getShipment()->getId() !== $shipmentId) {
            throw $this->createNotFoundException();
        }
        if ($milestone->getMilestoneCode()->isSystem()) {
            return $this->json(['error' => $this->trans('System milestones cannot be deleted.')], Response::HTTP_FORBIDDEN);
        }

        $this->milestoneRepository->delete($milestone);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrateFromBody(ShipmentMilestone $milestone, array $body): void
    {
        if (array_key_exists('plannedDate', $body)) {
            $milestone->setPlannedDate($body['plannedDate'] ? new \DateTime($body['plannedDate']) : null);
        }
        if (array_key_exists('actualDate', $body)) {
            $milestone->setActualDate($body['actualDate'] ? new \DateTime($body['actualDate']) : null);
        }
        if (isset($body['source'])) {
            $milestone->setSource($body['source']);
        }
        if (array_key_exists('remarks', $body)) {
            $milestone->setRemarks($body['remarks'] ?: null);
        }
        $milestone->recalculateException();

        $user = $this->getUser();
        if ($user) {
            $milestone->setUpdatedBy($user);
        }
    }

    private function serialize(ShipmentMilestone $m): array
    {
        $u = $m->getUpdatedBy();
        return [
            'id'             => $m->getId(),
            'milestoneCode'  => $m->getMilestoneCode()->value,
            'description'    => $m->getMilestoneCode()->description(),
            'isSystem'       => $m->getMilestoneCode()->isSystem(),
            'plannedDate'    => $m->getPlannedDate()?->format(\DateTimeInterface::ATOM),
            'actualDate'     => $m->getActualDate()?->format(\DateTimeInterface::ATOM),
            'isException'    => $m->isException(),
            'exceptionHours' => $m->getExceptionHours() !== null ? (float) $m->getExceptionHours() : null,
            'source'         => $m->getSource(),
            'remarks'        => $m->getRemarks(),
            'updatedBy'      => $u ? ['id' => $u->getId(), 'name' => $u->getFullName()] : null,
        ];
    }
}
