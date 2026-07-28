<?php
namespace App\Module\Insurance\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Carrier\Entity\Provider;
use App\Module\Insurance\Entity\InsurancePolicy;
use App\Module\Insurance\Repository\InsurancePolicyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/insurance/policy')]
#[IsGranted('ROLE_USER')]
#[AppModule('insurance')]
class InsurancePolicyController extends AbstractController
{
    public function __construct(
        private readonly InsurancePolicyRepository $repo,
        private readonly EntityManagerInterface    $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $active = $request->query->get('active');
        if ($active !== null) {
            $policies = $this->repo->findActive();
            return $this->json(array_map(fn($p) => $p->toArray(), $policies));
        }
        $list = array_map(fn($p) => $p->toArray(), $this->repo->findBy([], ['policyNumber' => 'ASC']));
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(InsurancePolicy $policy): JsonResponse
    {
        return $this->json($policy->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $policy = $this->hydrate(new InsurancePolicy(), $data);
        $policy->setCreatedAt(new \DateTime());
        $this->repo->save($policy);
        return $this->json($policy->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(InsurancePolicy $policy, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($policy, $data);
        $this->repo->save($policy);
        return $this->json($policy->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(InsurancePolicy $policy): JsonResponse
    {
        $this->repo->remove($policy);
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

    private function hydrate(InsurancePolicy $policy, array $data): InsurancePolicy
    {
        $policy->setPolicyNumber($data['policyNumber']);
        $policy->setPolicyType($data['policyType'] ?? 'OPEN_COVER');
        $policy->setCoverageScope($data['coverageScope'] ?? 'ALL_RISK');
        $policy->setMaxPerShipment((float) $data['maxPerShipment']);
        $policy->setMaxPerConveyance(isset($data['maxPerConveyance']) ? (float) $data['maxPerConveyance'] : null);
        $policy->setAnnualLimit(isset($data['annualLimit']) ? (float) $data['annualLimit'] : null);
        $policy->setCurrency(strtoupper($data['currency']));
        $policy->setPremiumBasis($data['premiumBasis'] ?? 'PCT_VALUE');
        $policy->setPremiumRate((float) $data['premiumRate']);
        $policy->setMinPremium(isset($data['minPremium']) ? (float) $data['minPremium'] : null);
        $policy->setDeductible(isset($data['deductible']) ? (float) $data['deductible'] : null);
        $policy->setModesCovered($data['modesCovered'] ?? []);
        $policy->setEffectiveFrom(new \DateTime($data['effectiveFrom']));
        $policy->setExpiryDate(new \DateTime($data['expiryDate']));
        $policy->setIsActive($data['isActive'] ?? true);

        $insurerId = $data['insurerId'] ?? null;
        $policy->setInsurer($insurerId ? $this->em->getReference(Provider::class, (int) $insurerId) : null);

        return $policy;
    }
}
