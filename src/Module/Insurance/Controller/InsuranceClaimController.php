<?php
namespace App\Module\Insurance\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Carrier\Entity\Provider;
use App\Module\Insurance\Entity\InsuranceClaim;
use App\Module\Insurance\Entity\InsuranceCertificate;
use App\Module\Insurance\Repository\InsuranceClaimRepository;
use App\Module\Operations\Entity\Shipment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/insurance/claim')]
#[IsGranted('ROLE_USER')]
#[AppModule('insurance')]
class InsuranceClaimController extends AbstractController
{
    public function __construct(
        private readonly InsuranceClaimRepository $repo,
        private readonly EntityManagerInterface   $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $certId = $request->query->get('certificateId');
        if ($certId) {
            $claims = $this->repo->findByCertificate((int) $certId);
        } else {
            $claims = $this->repo->findBy([], ['incidentDate' => 'DESC'], 200);
        }
        return $this->json(array_map(fn($c) => $c->toArray(), $claims));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(InsuranceClaim $claim): JsonResponse
    {
        return $this->json($claim->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent(), true);
        $claim = $this->hydrate(new InsuranceClaim(), $data);
        $claim->setClaimNumber($this->repo->generateClaimNumber());
        $claim->setCreatedAt(new \DateTime());
        $this->repo->save($claim);
        return $this->json($claim->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(InsuranceClaim $claim, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($claim, $data);
        $this->repo->save($claim);
        return $this->json($claim->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(InsuranceClaim $claim): JsonResponse
    {
        $this->repo->remove($claim);
        return $this->json(null, 204);
    }

    private function hydrate(InsuranceClaim $claim, array $data): InsuranceClaim
    {
        $claim->setCertificate($this->em->getReference(InsuranceCertificate::class, (int) $data['certificateId']));
        $claim->setShipment($this->em->getReference(Shipment::class, (int) $data['shipmentId']));
        $claim->setClaimType($data['claimType']);
        $claim->setIncidentDate(new \DateTime($data['incidentDate']));
        $claim->setIncidentLocation($data['incidentLocation'] ?? null);
        $claim->setDescription($data['description']);
        $claim->setClaimedAmount((float) $data['claimedAmount']);
        $claim->setCurrency(strtoupper($data['currency']));
        $claim->setStatus($data['status'] ?? 'FILED');
        $claim->setSurveyorRef($data['surveyorRef'] ?? null);
        $claim->setApprovedAmount(isset($data['approvedAmount']) ? (float) $data['approvedAmount'] : null);
        $claim->setDeductibleApplied(isset($data['deductibleApplied']) ? (float) $data['deductibleApplied'] : null);
        $claim->setNetSettlement(isset($data['netSettlement']) ? (float) $data['netSettlement'] : null);
        $claim->setSettledDate(isset($data['settledDate']) && $data['settledDate'] ? new \DateTime($data['settledDate']) : null);
        $claim->setRejectionReason($data['rejectionReason'] ?? null);

        $surveyorId = $data['surveyorId'] ?? null;
        $claim->setSurveyor($surveyorId ? $this->em->getReference(Provider::class, (int) $surveyorId) : null);

        return $claim;
    }
}
