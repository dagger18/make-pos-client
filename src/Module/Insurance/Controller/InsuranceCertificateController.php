<?php
namespace App\Module\Insurance\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Core\Entity\User;
use App\Module\Insurance\Entity\InsuranceCertificate;
use App\Module\Insurance\Entity\InsurancePolicy;
use App\Module\Insurance\Repository\InsuranceCertificateRepository;
use App\Module\Insurance\Service\PremiumCalculationService;
use App\Module\Operations\Entity\Shipment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/insurance/certificate')]
#[IsGranted('ROLE_USER')]
#[AppModule('insurance')]
class InsuranceCertificateController extends AbstractController
{
    public function __construct(
        private readonly InsuranceCertificateRepository $repo,
        private readonly EntityManagerInterface         $em,
        private readonly PremiumCalculationService      $premiumService,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $shipmentId = $request->query->get('shipmentId');
        if ($shipmentId) {
            $certs = $this->repo->findByShipment((int) $shipmentId);
        } else {
            $certs = $this->repo->findBy([], ['issueDate' => 'DESC'], 200);
        }
        return $this->json(array_map(fn($c) => $c->toArray(), $certs));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(InsuranceCertificate $cert): JsonResponse
    {
        return $this->json($cert->toArray());
    }

    #[Route('/calculate-premium', methods: ['POST'])]
    public function calculatePremium(Request $request): JsonResponse
    {
        $data      = json_decode($request->getContent(), true);
        $policyId  = (int) ($data['policyId'] ?? 0);
        $policy    = $this->em->find(InsurancePolicy::class, $policyId);
        if (!$policy) {
            return $this->json(['error' => $this->trans('Policy not found')], 404);
        }
        $cargoValue = (float) ($data['cargoValue'] ?? 0);
        return $this->json($this->premiumService->calculate($cargoValue, $policy));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $cert = $this->hydrate(new InsuranceCertificate(), $data);
        $cert->setCertificateNumber($this->repo->generateCertificateNumber());
        $cert->setIssuedBy($user);
        $cert->setCreatedAt(new \DateTime());
        $this->repo->save($cert);
        return $this->json($cert->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(InsuranceCertificate $cert, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($cert, $data);
        $this->repo->save($cert);
        return $this->json($cert->toArray());
    }

    #[Route('/{id}/cancel', methods: ['POST'])]
    public function cancel(InsuranceCertificate $cert): JsonResponse
    {
        $cert->setStatus('CANCELLED');
        $this->repo->save($cert);
        return $this->json($cert->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(InsuranceCertificate $cert): JsonResponse
    {
        $this->repo->remove($cert);
        return $this->json(null, 204);
    }

    private function hydrate(InsuranceCertificate $cert, array $data): InsuranceCertificate
    {
        $cert->setShipment($this->em->getReference(Shipment::class, (int) $data['shipmentId']));
        $cert->setPolicy($this->em->getReference(InsurancePolicy::class, (int) $data['policyId']));
        $cert->setInsuredName($data['insuredName']);
        $cert->setBeneficiaryName($data['beneficiaryName'] ?? null);
        $cert->setGoodsDescription($data['goodsDescription']);
        $cert->setPacking($data['packing'] ?? null);
        $cert->setMarksNumbers($data['marksNumbers'] ?? null);
        $cert->setHsCode($data['hsCode'] ?? null);
        $cert->setVesselOrFlight($data['vesselOrFlight'] ?? null);
        $cert->setVoyageOrFlightNo($data['voyageOrFlightNo'] ?? null);
        $cert->setPolName($data['polName']);
        $cert->setPodName($data['podName']);
        $cert->setEtd(isset($data['etd']) && $data['etd'] ? new \DateTime($data['etd']) : null);
        $cert->setCargoValue((float) $data['cargoValue']);
        $cert->setValueCurrency(strtoupper($data['valueCurrency']));
        $cert->setInsuredAmount((float) $data['insuredAmount']);
        $cert->setPremiumAmount((float) $data['premiumAmount']);
        $cert->setPremiumCurrency(strtoupper($data['premiumCurrency']));
        $cert->setCoverageScope($data['coverageScope'] ?? 'ALL_RISK');
        $cert->setStatus($data['status'] ?? 'ISSUED');
        $cert->setIssueDate(new \DateTime($data['issueDate'] ?? 'today'));
        $cert->setIsInvoiced($data['isInvoiced'] ?? false);
        return $cert;
    }
}
