<?php
namespace App\Module\Crm\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Core\Entity\User;
use App\Module\Crm\Entity\SalesCommission;
use App\Module\Crm\Repository\SalesCommissionRepository;
use App\Module\Operations\Entity\Shipment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/crm/commission')]
#[IsGranted('ROLE_USER')]
#[AppModule('crm')]
class SalesCommissionController extends AbstractController
{
    public function __construct(
        private readonly SalesCommissionRepository $repo,
        private readonly EntityManagerInterface    $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $repId  = $request->query->get('repId');
        $period = $request->query->get('period');
        $status = $request->query->get('status');

        if ($repId) {
            $items = $this->repo->findByRep((int) $repId, $period ?: null);
        } else {
            $criteria = $status ? ['status' => $status] : [];
            $items    = $this->repo->findBy($criteria, ['createdAt' => 'DESC'], 200);
        }
        return $this->json(array_map(fn($c) => $c->toArray(), $items));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(SalesCommission $commission): JsonResponse
    {
        return $this->json($commission->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data       = json_decode($request->getContent(), true);
        $commission = $this->hydrate(new SalesCommission(), $data);
        $commission->setCreatedAt(new \DateTime());
        $this->repo->save($commission);
        return $this->json($commission->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(SalesCommission $commission, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($commission, $data);
        $this->repo->save($commission);
        return $this->json($commission->toArray());
    }

    #[Route('/{id}/approve', methods: ['POST'])]
    public function approve(SalesCommission $commission): JsonResponse
    {
        $commission->setStatus('APPROVED');
        $this->repo->save($commission);
        return $this->json($commission->toArray());
    }

    #[Route('/{id}/pay', methods: ['POST'])]
    public function pay(SalesCommission $commission): JsonResponse
    {
        $commission->setStatus('PAID');
        $this->repo->save($commission);
        return $this->json($commission->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(SalesCommission $commission): JsonResponse
    {
        $this->repo->remove($commission);
        return $this->json(null, 204);
    }

    private function hydrate(SalesCommission $commission, array $data): SalesCommission
    {
        $commission->setSalesRep($this->em->getReference(User::class, (int) $data['salesRepId']));
        $commission->setShipment($this->em->getReference(Shipment::class, (int) $data['shipmentId']));
        $commission->setCommissionBasis($data['commissionBasis']);
        $commission->setCommissionRate(isset($data['commissionRate']) ? (float) $data['commissionRate'] : null);
        $commission->setBaseAmount((float) $data['baseAmount']);
        $commission->setCommissionAmount((float) $data['commissionAmount']);
        $commission->setCurrency(strtoupper($data['currency']));
        $commission->setStatus($data['status'] ?? 'CALCULATED');
        $commission->setPeriodMonth($data['periodMonth'] ?? null);
        return $commission;
    }
}
