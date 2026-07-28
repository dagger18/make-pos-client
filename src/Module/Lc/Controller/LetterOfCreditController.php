<?php
declare(strict_types=1);
namespace App\Module\Lc\Controller;

use App\Attribute\AppModule;
use App\Module\Lc\Entity\LetterOfCredit;
use App\Module\Lc\Repository\LetterOfCreditRepository;
use App\Module\Lc\Service\LcComplianceService;
use App\Module\Operations\Entity\Shipment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AppModule('lc')]
#[Route('/lc')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class LetterOfCreditController extends AbstractController
{
    public function __construct(
        private readonly LetterOfCreditRepository $repo,
        private readonly EntityManagerInterface   $em,
        private readonly LcComplianceService      $complianceService,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $lcs = $this->repo->search($request->query->all());
        return $this->json(array_map(fn($lc) => $lc->toArray(), $lcs));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $lc = $this->repo->find($id);
        if (!$lc) return $this->json(['error' => $this->trans('Not found')], 404);
        return $this->json($lc->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $shipment = $this->em->getReference(Shipment::class, $data['shipmentId']);

        $lc = (new LetterOfCredit())
            ->setShipment($shipment)
            ->setLcNumber($data['lcNumber'])
            ->setLcType($data['lcType'] ?? 'IRREVOCABLE')
            ->setIssuingBankName($data['issuingBankName'])
            ->setAdvisingBankName($data['advisingBankName'] ?? null)
            ->setNegotiatingBankName($data['negotiatingBankName'] ?? null)
            ->setApplicantName($data['applicantName'])
            ->setBeneficiaryName($data['beneficiaryName'])
            ->setLcAmount((string)$data['lcAmount'])
            ->setLcCurrency(strtoupper($data['lcCurrency']))
            ->setIssueDate(new \DateTime($data['issueDate']))
            ->setExpiryDate(new \DateTime($data['expiryDate']))
            ->setExpiryPlace($data['expiryPlace'])
            ->setShipmentBy(new \DateTime($data['shipmentBy']))
            ->setPresentationDays((int)($data['presentationDays'] ?? 21))
            ->setSpecialConditions($data['specialConditions'] ?? null)
            ->setPartialShipments($data['partialShipments'] ?? 'NOT_ALLOWED')
            ->setTranshipment($data['transhipment'] ?? 'NOT_ALLOWED')
            ->setStatus($data['status'] ?? 'OPEN');

        $this->em->persist($lc);
        $this->em->flush();

        return $this->json($lc->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $lc = $this->repo->find($id);
        if (!$lc) return $this->json(['error' => $this->trans('Not found')], 404);

        $data = json_decode($request->getContent(), true);

        if (isset($data['lcNumber']))           $lc->setLcNumber($data['lcNumber']);
        if (isset($data['lcType']))             $lc->setLcType($data['lcType']);
        if (array_key_exists('issuingBankName', $data))    $lc->setIssuingBankName($data['issuingBankName']);
        if (array_key_exists('advisingBankName', $data))   $lc->setAdvisingBankName($data['advisingBankName']);
        if (array_key_exists('negotiatingBankName', $data)) $lc->setNegotiatingBankName($data['negotiatingBankName']);
        if (isset($data['applicantName']))      $lc->setApplicantName($data['applicantName']);
        if (isset($data['beneficiaryName']))    $lc->setBeneficiaryName($data['beneficiaryName']);
        if (isset($data['lcAmount']))           $lc->setLcAmount((string)$data['lcAmount']);
        if (isset($data['lcCurrency']))         $lc->setLcCurrency(strtoupper($data['lcCurrency']));
        if (isset($data['issueDate']))          $lc->setIssueDate(new \DateTime($data['issueDate']));
        if (isset($data['expiryDate']))         $lc->setExpiryDate(new \DateTime($data['expiryDate']));
        if (isset($data['expiryPlace']))        $lc->setExpiryPlace($data['expiryPlace']);
        if (isset($data['shipmentBy']))         $lc->setShipmentBy(new \DateTime($data['shipmentBy']));
        if (isset($data['presentationDays']))   $lc->setPresentationDays((int)$data['presentationDays']);
        if (array_key_exists('specialConditions', $data))  $lc->setSpecialConditions($data['specialConditions']);
        if (isset($data['partialShipments']))   $lc->setPartialShipments($data['partialShipments']);
        if (isset($data['transhipment']))       $lc->setTranshipment($data['transhipment']);
        if (isset($data['status']))             $lc->setStatus($data['status']);

        $this->em->flush();
        return $this->json($lc->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $lc = $this->repo->find($id);
        if (!$lc) return $this->json(['error' => $this->trans('Not found')], 404);
        $this->em->remove($lc);
        $this->em->flush();
        return $this->json(null, 204);
    }

    /** Set the BL on-board date → recalculate and save presentation_deadline */
    #[Route('/{id}/set-bl-date', methods: ['POST'])]
    public function setBlDate(int $id, Request $request): JsonResponse
    {
        $lc = $this->repo->find($id);
        if (!$lc) return $this->json(['error' => $this->trans('Not found')], 404);

        $data   = json_decode($request->getContent(), true);
        $blDate = new \DateTime($data['blOnBoardDate']);

        $deadline = $lc->calcPresentationDeadline($blDate);
        $lc->setPresentationDeadline($deadline);
        $this->em->flush();

        return $this->json([
            'blOnBoardDate'        => $blDate->format('Y-m-d'),
            'presentationDeadline' => $deadline->format('Y-m-d'),
            'daysRemaining'        => (int)(new \DateTime())->diff($deadline)->days * ($deadline > new \DateTime() ? 1 : -1),
        ]);
    }

    /** Run compliance checks against the LC terms and persist failed checks as discrepancies */
    #[Route('/{id}/compliance-check', methods: ['POST'])]
    public function complianceCheck(int $id, Request $request): JsonResponse
    {
        $lc = $this->repo->find($id);
        if (!$lc) return $this->json(['error' => $this->trans('Not found')], 404);

        $params  = json_decode($request->getContent(), true);
        $checks  = $this->complianceService->runChecks($lc, $params);
        $created = $this->complianceService->persistFailedChecks($lc, $checks);
        $status  = $this->complianceService->overallStatus($checks);

        return $this->json([
            'status'               => $status,
            'checks'               => $checks,
            'discrepanciesCreated' => $created,
        ]);
    }
}
