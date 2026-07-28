<?php
namespace App\Module\Carrier\Controller;

use App\Module\Carrier\Entity\CargoClaim;
use App\Module\Carrier\Entity\Provider;
use App\Module\Operations\Entity\Shipment;
use App\Module\Carrier\Repository\CargoClaimRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cargo-claim')]
#[IsGranted('ROLE_USER')]
#[AppModule('carrier')]
class CargoClaimController extends AbstractController
{
    public function __construct(
        private readonly CargoClaimRepository   $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $carrierId = $request->query->get('carrierId');
        $claims = $carrierId
            ? $this->repo->findByCarrier((int) $carrierId)
            : $this->repo->findBy([], ['claimDate' => 'DESC']);
        $list = array_map(fn($c) => $c->toArray(), $claims);
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(CargoClaim $claim): JsonResponse
    {
        return $this->json($claim->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent(), true);
        $claim = $this->hydrate(new CargoClaim(), $data);
        $claim->setCreatedAt(new \DateTime());
        $this->repo->save($claim);
        return $this->json($claim->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(CargoClaim $claim, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($claim, $data);
        $this->repo->save($claim);
        return $this->json($claim->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(CargoClaim $claim): JsonResponse
    {
        $this->repo->remove($claim);
        return $this->json(null, 204);
    }

    private function paginate(array $items, Request $request): array
    {
        $limit = (int) ($request->query->get('limit') ?? 50);
        $page  = max(1, (int) ($request->query->get('page') ?? 1));
        $total = count($items);
        if ($limit <= 0) {
            return ['list' => $items, 'total' => $total, 'totalPages' => 1, 'currentPage' => 1];
        }
        return [
            'list'        => array_values(array_slice($items, ($page - 1) * $limit, $limit)),
            'total'       => $total,
            'totalPages'  => (int) ceil($total / $limit),
            'currentPage' => $page,
        ];
    }

    private function hydrate(CargoClaim $claim, array $data): CargoClaim
    {
        $claim->setShipment($this->em->getReference(Shipment::class, $data['shipmentId']));
        $claim->setCarrier($this->em->getReference(Provider::class, $data['carrierId']));
        $claim->setTransportMode($data['transportMode'] ?? 'OCN');
        $claim->setClaimType($data['claimType']);
        $claim->setClaimDate(new \DateTime($data['claimDate']));
        $claim->setClaimAmount((float) $data['claimAmount']);
        $claim->setCurrency($data['currency']);
        $claim->setDescription($data['description'] ?? null);
        $claim->setStatus($data['status'] ?? 'OPEN');
        $claim->setSettlementAmount(isset($data['settlementAmount']) && $data['settlementAmount'] !== null ? (float) $data['settlementAmount'] : null);
        $claim->setSettledAt(isset($data['settledAt']) && $data['settledAt'] ? new \DateTime($data['settledAt']) : null);
        return $claim;
    }
}
