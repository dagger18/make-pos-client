<?php
namespace App\Module\Tax\Controller;

use App\Module\Tax\Entity\PartnerTaxRegistration;
use App\Module\Crm\Entity\Partner;
use App\Module\Tax\Repository\PartnerTaxRegistrationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[AppModule('tax')]
class PartnerTaxRegistrationController extends AbstractController
{
    public function __construct(private readonly PartnerTaxRegistrationRepository $repo) {}

    #[Route('/partner/{id}/tax-registration', methods: ['GET'])]
    public function listByPartner(Partner $partner, Request $request): JsonResponse
    {
        $list = array_map(fn($r) => $r->toArray(), $this->repo->findForPartner($partner->getId()));
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/partner/{id}/tax-registration', methods: ['POST'])]
    public function create(Partner $partner, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reg = $this->hydrate(new PartnerTaxRegistration(), $data, $partner);
        $this->repo->save($reg);
        return $this->json($reg->toArray(), 201);
    }

    #[Route('/tax-registration/{id}', methods: ['PUT'])]
    public function update(PartnerTaxRegistration $reg, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($reg, $data, $reg->getPartner());
        $this->repo->save($reg);
        return $this->json($reg->toArray());
    }

    #[Route('/tax-registration/{id}', methods: ['DELETE'])]
    public function delete(PartnerTaxRegistration $reg): JsonResponse
    {
        $this->repo->remove($reg);
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

    private function hydrate(PartnerTaxRegistration $r, array $data, Partner $partner): PartnerTaxRegistration
    {
        $r->setPartner($partner);
        $r->setCountryCode($data['countryCode']);
        $r->setTaxType($data['taxType']);
        $r->setRegistrationNo($data['registrationNo']);
        $r->setIsPrimary((bool) ($data['isPrimary'] ?? false));
        $r->setEffectiveFrom(new \DateTime($data['effectiveFrom']));
        $r->setEffectiveTo(isset($data['effectiveTo']) && $data['effectiveTo'] ? new \DateTime($data['effectiveTo']) : null);
        return $r;
    }
}
