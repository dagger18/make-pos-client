<?php
namespace App\Module\Loyalty\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Loyalty\Entity\LoyaltyCustomer;
use App\Module\Loyalty\Repository\LoyaltyCustomerRepository;
use App\Module\Loyalty\Repository\LoyaltyTransactionRepository;
use App\Module\Loyalty\Service\LoyaltyService;
use App\Module\Core\Controller\CrudController;
use App\Module\Core\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/loyalty/customer')]
#[IsGranted('ROLE_USER')]
#[AppModule('loyalty')]
class LoyaltyController extends CrudController
{
    public function __construct(
        BaseService $baseService,
        private readonly LoyaltyCustomerRepository $repo,
        private readonly LoyaltyTransactionRepository $txRepo,
        private readonly LoyaltyService $loyaltyService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($baseService);
    }

    #[Route('', methods: ['GET'])]
    public function LIST(Request $request): JsonResponse
    {
        $query = $request->query;

        return $this->json($this->repo->getList($query->all(), 'list', function (QueryBuilder $qb) use ($query): void {
            if ($q = $query->get('q')) {
                $qb->andWhere('LoyaltyCustomer.name LIKE :q OR LoyaltyCustomer.phone LIKE :q OR LoyaltyCustomer.email LIKE :q')
                   ->setParameter('q', '%' . $q . '%');
            }
        }));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function DETAIL(int $id): JsonResponse
    {
        $customer = $this->repo->find($id);
        if (!$customer) {
            return $this->json(['error' => 'Not found.'], 404);
        }
        $transactions = $this->txRepo->findBy(['customer' => $customer], ['id' => 'DESC'], 20);
        return $this->json(array_merge($customer->toArray(), [
            'transactions' => array_map(fn ($t) => $t->toArray(), $transactions),
        ]));
    }

    #[Route('', methods: ['POST'])]
    public function POST(Request $request): JsonResponse
    {
        $data     = $request->toArray();
        $customer = new LoyaltyCustomer();
        $customer->setName($data['name'] ?? '');
        $customer->setPhone($data['phone'] ?? null);
        $customer->setEmail($data['email'] ?? null);

        $this->em->persist($customer);
        $this->em->flush();

        return $this->json($customer->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function PUT(int $id, Request $request): JsonResponse
    {
        $customer = $this->repo->find($id);
        if (!$customer) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $data = $request->toArray();
        if (isset($data['name']))  $customer->setName($data['name']);
        if (array_key_exists('phone', $data)) $customer->setPhone($data['phone'] ?? null);
        if (array_key_exists('email', $data)) $customer->setEmail($data['email'] ?? null);

        $this->em->flush();

        return $this->json($customer->toArray());
    }

    #[Route('/{id}/earn', methods: ['POST'])]
    public function EARN(int $id, Request $request): JsonResponse
    {
        $customer = $this->repo->find($id);
        if (!$customer) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $data      = $request->toArray();
        $points    = (int) ($data['points'] ?? 0);
        $reference = $data['reference'] ?? null;

        $tx = $this->loyaltyService->earn($customer, $points, $reference);
        $this->em->persist($tx);
        $this->em->flush();

        return $this->json(array_merge($customer->toArray(), ['transaction' => $tx->toArray()]), 201);
    }

    #[Route('/{id}/redeem', methods: ['POST'])]
    public function REDEEM(int $id, Request $request): JsonResponse
    {
        $customer = $this->repo->find($id);
        if (!$customer) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $data      = $request->toArray();
        $points    = (int) ($data['points'] ?? 0);
        $reference = $data['reference'] ?? null;

        try {
            $tx = $this->loyaltyService->redeem($customer, $points, $reference);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        $this->em->persist($tx);
        $this->em->flush();

        return $this->json(array_merge($customer->toArray(), ['transaction' => $tx->toArray()]), 201);
    }
}
