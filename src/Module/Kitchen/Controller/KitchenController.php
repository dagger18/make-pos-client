<?php
namespace App\Module\Kitchen\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Kitchen\Entity\KitchenTicket;
use App\Module\Kitchen\Enum\KitchenStatus;
use App\Module\Kitchen\Repository\KitchenTicketRepository;
use App\Module\Kitchen\Service\KitchenService;
use App\Module\Sales\Repository\OrderRepository;
use App\Module\Core\Controller\CrudController;
use App\Module\Core\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/kitchen')]
#[IsGranted('ROLE_USER')]
#[AppModule('kitchen')]
class KitchenController extends CrudController
{
    public function __construct(
        BaseService $baseService,
        private readonly KitchenTicketRepository $repo,
        private readonly OrderRepository $orderRepo,
        private readonly KitchenService $kitchenService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($baseService);
    }

    #[Route('', methods: ['GET'])]
    public function LIST(Request $request): JsonResponse
    {
        $query = $request->query;

        return $this->json($this->repo->getList($query->all(), 'list', function (QueryBuilder $qb) use ($query): void {
            if ($status = $query->get('status')) {
                $qb->andWhere('KitchenTicket.status = :status')
                   ->setParameter('status', $status);
            }
        }));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function DETAIL(int $id): JsonResponse
    {
        $ticket = $this->repo->find($id);
        if (!$ticket) {
            return $this->json(['error' => 'Not found.'], 404);
        }
        return $this->json($ticket->toArray());
    }

    #[Route('/from-order/{orderId}', methods: ['POST'])]
    public function CREATE_FROM_ORDER(int $orderId): JsonResponse
    {
        $order = $this->orderRepo->find($orderId);
        if (!$order) {
            return $this->json(['error' => 'Order not found.'], 404);
        }

        $existing = $this->repo->findOneBy(['order' => $order]);
        if ($existing) {
            return $this->json(['error' => 'Ticket already exists for this order.', 'id' => $existing->getId()], 409);
        }

        $ticket = new KitchenTicket();
        $ticket->setOrder($order);

        $this->em->persist($ticket);
        $this->em->flush();

        return $this->json($ticket->toArray(), 201);
    }

    #[Route('/{id}/advance', methods: ['POST'])]
    public function ADVANCE(int $id): JsonResponse
    {
        $ticket = $this->repo->find($id);
        if (!$ticket) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $this->kitchenService->advance($ticket);
        $this->em->flush();

        return $this->json($ticket->toArray());
    }
}
