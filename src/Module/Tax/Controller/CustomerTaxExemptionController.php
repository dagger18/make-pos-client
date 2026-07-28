<?php
namespace App\Module\Tax\Controller;

use App\Module\Tax\Entity\CustomerTaxExemption;
use App\Module\Crm\Entity\Partner;
use App\Module\Core\Entity\User;
use App\Module\Tax\Repository\CustomerTaxExemptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[AppModule('tax')]
class CustomerTaxExemptionController extends AbstractController
{
    public function __construct(
        private readonly CustomerTaxExemptionRepository $repo,
        private readonly EntityManagerInterface $em
    ) {}

    #[Route('/partner/{id}/tax-exemption', methods: ['GET'])]
    public function listByPartner(Partner $partner, Request $request): JsonResponse
    {
        $list = array_map(fn($e) => $e->toArray(), $this->repo->findByPartner($partner->getId()));
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/partner/{id}/tax-exemption', methods: ['POST'])]
    public function create(Partner $partner, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $exemption = $this->hydrate(new CustomerTaxExemption(), $data, $partner);
        $this->repo->save($exemption);
        return $this->json($exemption->toArray(), 201);
    }

    #[Route('/tax-exemption/{id}', methods: ['PUT'])]
    public function update(CustomerTaxExemption $exemption, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($exemption, $data, $exemption->getPartner());
        $this->repo->save($exemption);
        return $this->json($exemption->toArray());
    }

    #[Route('/tax-exemption/{id}', methods: ['DELETE'])]
    public function delete(CustomerTaxExemption $exemption): JsonResponse
    {
        $this->repo->remove($exemption);
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

    private function hydrate(CustomerTaxExemption $e, array $data, Partner $partner): CustomerTaxExemption
    {
        $e->setPartner($partner);
        $e->setExemptionType($data['exemptionType']);
        $e->setCountryCode($data['countryCode']);
        $e->setExemptionRef($data['exemptionRef'] ?? null);
        $e->setValidFrom(new \DateTime($data['validFrom']));
        $e->setValidTo(isset($data['validTo']) && $data['validTo'] ? new \DateTime($data['validTo']) : null);
        $e->setDocumentUrl($data['documentUrl'] ?? null);
        if (!empty($data['verifiedById'])) {
            $e->setVerifiedBy($this->em->getReference(User::class, $data['verifiedById']));
        }
        $e->setVerifiedAt(isset($data['verifiedAt']) && $data['verifiedAt'] ? new \DateTime($data['verifiedAt']) : null);
        return $e;
    }
}
