<?php
namespace App\Module\Finance\Controller;

use App\Module\Finance\Entity\TaxRule;
use App\Module\Finance\Repository\TaxRuleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tax-rule')]
#[IsGranted('ROLE_USER')]
#[AppModule('finance')]
class TaxRuleController extends AbstractController
{
    public function __construct(private readonly TaxRuleRepository $repo) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $list = array_map(fn($r) => $r->toArray(), $this->repo->findAllOrdered());
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/lookup', methods: ['GET'])]
    public function lookup(Request $request): JsonResponse
    {
        $rule = $this->repo->findMostSpecific(
            $request->query->get('countryCode', ''),
            $request->query->get('chargeCategory'),
            $request->query->get('serviceType'),
            $request->query->get('customerType'),
            $request->query->get('date', date('Y-m-d'))
        );
        return $this->json($rule ? $rule->toArray() : null);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(TaxRule $taxRule): JsonResponse
    {
        return $this->json($taxRule->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $rule = $this->hydrate(new TaxRule(), $data);
        $this->repo->save($rule);
        return $this->json($rule->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(TaxRule $taxRule, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($taxRule, $data);
        $this->repo->save($taxRule);
        return $this->json($taxRule->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(TaxRule $taxRule): JsonResponse
    {
        $this->repo->remove($taxRule);
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

    private function hydrate(TaxRule $rule, array $data): TaxRule
    {
        $rule->setCountryCode($data['countryCode']);
        $rule->setChargeCategory($data['chargeCategory'] ?? null);
        $rule->setServiceType($data['serviceType'] ?? null);
        $rule->setCustomerType($data['customerType'] ?? null);
        $rule->setTaxType($data['taxType']);
        $rule->setTaxCode($data['taxCode']);
        $rule->setTaxRate((float) ($data['taxRate'] ?? 0));
        $rule->setIsReverseCharge((bool) ($data['isReverseCharge'] ?? false));
        $rule->setIsZeroRated((bool) ($data['isZeroRated'] ?? false));
        $rule->setIsExempt((bool) ($data['isExempt'] ?? false));
        $rule->setDescription($data['description'] ?? null);
        $rule->setEffectiveFrom(new \DateTime($data['effectiveFrom']));
        $rule->setEffectiveTo(isset($data['effectiveTo']) && $data['effectiveTo'] ? new \DateTime($data['effectiveTo']) : null);
        return $rule;
    }
}
