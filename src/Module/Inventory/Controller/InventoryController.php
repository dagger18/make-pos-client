<?php
namespace App\Module\Inventory\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Catalog\Repository\ProductRepository;
use App\Module\Inventory\Entity\StockLevel;
use App\Module\Inventory\Enum\MovementType;
use App\Module\Inventory\Repository\StockLevelRepository;
use App\Module\Inventory\Repository\StockMovementRepository;
use App\Module\Inventory\Service\InventoryService;
use App\Module\Core\Controller\CrudController;
use App\Module\Core\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventory')]
#[IsGranted('ROLE_USER')]
#[AppModule('inventory')]
class InventoryController extends CrudController
{
    public function __construct(
        BaseService $baseService,
        private readonly StockLevelRepository $repo,
        private readonly StockMovementRepository $movementRepo,
        private readonly ProductRepository $productRepo,
        private readonly InventoryService $inventoryService,
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
                $qb->andWhere('StockLevel.location = :location')
                   ->setParameter('location', $location);
            }
            if ($q = $query->get('q')) {
                $qb->andWhere($qb->expr()->orX(
                    $qb->expr()->like('StockLevel.productName', ':q'),
                    $qb->expr()->like('StockLevel.productSku', ':q'),
                ))->setParameter('q', '%' . $q . '%');
            }
            if ($query->get('low_stock') === '1') {
                $qb->andWhere('StockLevel.lowStockThreshold IS NOT NULL')
                   ->andWhere('StockLevel.quantity <= StockLevel.lowStockThreshold');
            }
        }));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function DETAIL(int $id): JsonResponse
    {
        $sl = $this->repo->find($id);
        if (!$sl) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $movements = $this->movementRepo->createQueryBuilder('StockMovement')
            ->andWhere('StockMovement.stockLevel = :sl')
            ->setParameter('sl', $sl)
            ->orderBy('StockMovement.createdDate', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $this->json([
            ...$sl->toArray(),
            'movements' => array_map(fn($m) => $m->toArray(), $movements),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function POST(Request $request): JsonResponse
    {
        $data     = $request->toArray();
        $user     = $this->getUser();
        $location = $user->getLocation();
        if (!$location) {
            return $this->json(['error' => 'User has no location assigned.'], 400);
        }

        $product = $this->productRepo->find((int) ($data['product_id'] ?? 0));
        if (!$product) {
            return $this->json(['error' => 'Product not found.'], 404);
        }

        $existing = $this->repo->findOneBy(['product' => $product, 'location' => $location]);
        if ($existing) {
            return $this->json($existing->toArray(), 200);
        }

        $sl = new StockLevel();
        $sl->setLocation($location);
        $sl->setProduct($product);
        $sl->setProductName($product->getName() ?? '');
        $sl->setProductSku($product->getSku() ?? '');
        $sl->setLowStockThreshold(isset($data['low_stock_threshold']) ? (int) $data['low_stock_threshold'] : null);

        $initialQty = (int) ($data['initial_quantity'] ?? 0);
        if ($initialQty !== 0) {
            $this->em->persist($sl);
            $this->em->flush();

            $movement = $this->inventoryService->apply($sl, $initialQty, MovementType::Receive, 'Initial stock', $user);
            $this->em->persist($movement);
        } else {
            $this->em->persist($sl);
        }

        $this->em->flush();

        return $this->json($sl->toArray(), 201);
    }

    #[Route('/{id}/adjust', methods: ['POST'])]
    public function ADJUST(int $id, Request $request): JsonResponse
    {
        $sl = $this->repo->find($id);
        if (!$sl) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $data  = $request->toArray();
        $delta = (int) ($data['quantity_delta'] ?? 0);
        if ($delta === 0) {
            return $this->json(['error' => 'quantity_delta cannot be zero.'], 400);
        }

        $type     = MovementType::from($data['type'] ?? 'adjustment');
        $movement = $this->inventoryService->apply($sl, $delta, $type, $data['notes'] ?? null, $this->getUser());

        $this->em->persist($movement);
        $this->em->flush();

        return $this->json([
            ...$sl->toArray(),
            'movement' => $movement->toArray(),
        ], 201);
    }
}
