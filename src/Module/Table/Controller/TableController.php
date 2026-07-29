<?php
namespace App\Module\Table\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Table\Entity\RestaurantTable;
use App\Module\Table\Enum\TableStatus;
use App\Module\Table\Repository\RestaurantTableRepository;
use App\Module\Table\Service\TableService;
use App\Module\Core\Controller\CrudController;
use App\Module\Core\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/table')]
#[IsGranted('ROLE_USER')]
#[AppModule('table')]
class TableController extends CrudController
{
    public function __construct(
        BaseService $baseService,
        private readonly RestaurantTableRepository $repo,
        private readonly TableService $tableService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($baseService);
    }

    #[Route('', methods: ['GET'])]
    public function LIST(Request $request): JsonResponse
    {
        $query    = $request->query;
        $location = $this->getUser()->getLocation();

        return $this->json($this->repo->getList($query->all(), 'list', function (QueryBuilder $qb) use ($location, $query): void {
            if ($location) {
                $qb->andWhere('RestaurantTable.location = :location')
                   ->setParameter('location', $location);
            }
            if ($status = $query->get('status')) {
                $qb->andWhere('RestaurantTable.status = :status')
                   ->setParameter('status', $status);
            }
        }));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function DETAIL(int $id): JsonResponse
    {
        $table = $this->repo->find($id);
        if (!$table) {
            return $this->json(['error' => 'Not found.'], 404);
        }
        return $this->json($table->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function POST(Request $request): JsonResponse
    {
        $user     = $this->getUser();
        $location = $user->getLocation();
        if (!$location) {
            return $this->json(['error' => 'User has no location assigned.'], 400);
        }

        $data  = $request->toArray();
        $table = new RestaurantTable();
        $table->setLocation($location);
        $table->setName($data['name'] ?? '');
        $table->setCapacity(isset($data['capacity']) ? (int) $data['capacity'] : null);
        if (!empty($data['notes'])) {
            $table->setNotes($data['notes']);
        }

        $this->em->persist($table);
        $this->em->flush();

        return $this->json($table->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function PUT(int $id, Request $request): JsonResponse
    {
        $table = $this->repo->find($id);
        if (!$table) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $data = $request->toArray();
        if (isset($data['name']))     $table->setName($data['name']);
        if (array_key_exists('capacity', $data)) $table->setCapacity($data['capacity'] !== null ? (int) $data['capacity'] : null);
        if (array_key_exists('notes', $data))    $table->setNotes($data['notes'] ?: null);

        $this->em->flush();

        return $this->json($table->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function DELETE(int $id): JsonResponse
    {
        $table = $this->repo->find($id);
        if (!$table) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $this->em->remove($table);
        $this->em->flush();

        return $this->json(null, 204);
    }

    #[Route('/{id}/status', methods: ['POST'])]
    public function SET_STATUS(int $id, Request $request): JsonResponse
    {
        $table = $this->repo->find($id);
        if (!$table) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $data   = $request->toArray();
        $status = TableStatus::from($data['status'] ?? 'available');
        $this->tableService->setStatus($table, $status, $data['notes'] ?? null);

        $this->em->flush();

        return $this->json($table->toArray());
    }
}
