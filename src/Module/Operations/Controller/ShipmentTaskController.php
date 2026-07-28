<?php

namespace App\Module\Operations\Controller;

use App\Module\Carrier\Enum\MilestoneCode;

use App\Module\Operations\Entity\ShipmentTask;
use App\Module\Operations\Enum\TaskType;
use App\Module\Operations\Repository\ShipmentMilestoneRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Operations\Repository\ShipmentTaskRepository;
use App\Module\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/tasks')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class ShipmentTaskController extends AbstractController
{
    public function __construct(
        private readonly ShipmentTaskRepository      $taskRepository,
        private readonly ShipmentMilestoneRepository $milestoneRepository,
        private readonly ShipmentRepository          $shipmentRepository,
        private readonly UserRepository              $userRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        return $this->json(array_map(
            fn($t) => $this->serialize($t),
            $this->taskRepository->findByShipment($shipmentId)
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];

        if (empty($body['title'])) {
            return $this->json(['error' => $this->trans('Title is required.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $taskType = TaskType::tryFrom($body['taskType'] ?? '') ?? TaskType::Other;

        $task = (new ShipmentTask())
            ->setShipment($shipment)
            ->setTitle($body['title'])
            ->setTaskType($taskType)
            ->setIsMandatory((bool) ($body['isMandatory'] ?? false))
            ->setMilestoneGate($body['milestoneGate'] ?? null);

        $this->hydrateOptional($task, $body);
        $this->taskRepository->save($task);

        return $this->json($this->serialize($task), Response::HTTP_CREATED);
    }

    #[Route('/{taskId}', methods: ['PATCH'])]
    public function update(int $shipmentId, int $taskId, Request $request): JsonResponse
    {
        $task = $this->findTask($shipmentId, $taskId);
        $body = json_decode($request->getContent(), true) ?? [];

        if (isset($body['title'])) $task->setTitle($body['title']);
        $this->hydrateOptional($task, $body);
        $this->taskRepository->save($task);

        return $this->json($this->serialize($task));
    }

    #[Route('/{taskId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $taskId): JsonResponse
    {
        $task = $this->findTask($shipmentId, $taskId);
        if ($task->isMandatory()) {
            return $this->json(['error' => $this->trans('Mandatory tasks cannot be deleted.')], Response::HTTP_FORBIDDEN);
        }
        $this->taskRepository->delete($task);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{taskId}/complete', methods: ['POST'])]
    public function complete(int $shipmentId, int $taskId): JsonResponse
    {
        $task = $this->findTask($shipmentId, $taskId);
        $task->setCompletedAt(new \DateTime());
        $task->setCompletedBy($this->getUser());
        $this->taskRepository->save($task);
        return $this->json($this->serialize($task));
    }

    #[Route('/{taskId}/reopen', methods: ['POST'])]
    public function reopen(int $shipmentId, int $taskId): JsonResponse
    {
        $task = $this->findTask($shipmentId, $taskId);
        $task->setCompletedAt(null);
        $task->setCompletedBy(null);
        $this->taskRepository->save($task);

        // Clear the gated milestone's actual_date to maintain consistency
        if ($task->getMilestoneGate() && $task->isMandatory()) {
            $ms = $this->milestoneRepository->findByShipmentAndCode(
                $shipmentId,
                MilestoneCode::from($task->getMilestoneGate())
            );
            if ($ms && $ms->getActualDate()) {
                $ms->setActualDate(null);
                $ms->recalculateException();
                $this->milestoneRepository->save($ms);
            }
        }

        return $this->json($this->serialize($task));
    }

    private function findTask(int $shipmentId, int $taskId): ShipmentTask
    {
        $task = $this->taskRepository->find($taskId);
        if (!$task || $task->getShipment()->getId() !== $shipmentId) {
            throw $this->createNotFoundException();
        }
        return $task;
    }

    private function hydrateOptional(ShipmentTask $task, array $body): void
    {
        if (array_key_exists('description', $body)) $task->setDescription($body['description'] ?: null);
        if (array_key_exists('dueDate', $body))     $task->setDueDate($body['dueDate'] ? new \DateTime($body['dueDate']) : null);
        if (array_key_exists('assignedTo', $body)) {
            $user = $body['assignedTo'] ? $this->userRepository->find((int) $body['assignedTo']) : null;
            $task->setAssignedTo($user);
        }
    }

    private function serialize(ShipmentTask $t): array
    {
        $a = $t->getAssignedTo();
        $c = $t->getCompletedBy();
        return [
            'id'            => $t->getId(),
            'title'         => $t->getTitle(),
            'description'   => $t->getDescription(),
            'taskType'      => $t->getTaskType()->value,
            'taskTypeLabel' => $t->getTaskType()->label(),
            'assignedTo'    => $a ? ['id' => $a->getId(), 'name' => $a->getFullName()] : null,
            'dueDate'       => $t->getDueDate()?->format(\DateTimeInterface::ATOM),
            'completedAt'   => $t->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'completedBy'   => $c ? ['id' => $c->getId(), 'name' => $c->getFullName()] : null,
            'isMandatory'   => $t->isMandatory(),
            'milestoneGate' => $t->getMilestoneGate(),
            'sortOrder'     => $t->getSortOrder(),
            'isOverdue'     => $t->isOverdue(),
        ];
    }
}
