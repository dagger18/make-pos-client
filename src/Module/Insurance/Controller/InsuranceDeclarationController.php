<?php
namespace App\Module\Insurance\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Insurance\Entity\InsuranceCertificate;
use App\Module\Insurance\Entity\InsuranceDeclaration;
use App\Module\Insurance\Entity\InsuranceDeclarationLine;
use App\Module\Insurance\Entity\InsurancePolicy;
use App\Module\Insurance\Repository\InsuranceCertificateRepository;
use App\Module\Insurance\Repository\InsuranceDeclarationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/insurance/declaration')]
#[IsGranted('ROLE_USER')]
#[AppModule('insurance')]
class InsuranceDeclarationController extends AbstractController
{
    public function __construct(
        private readonly InsuranceDeclarationRepository $repo,
        private readonly InsuranceCertificateRepository $certRepo,
        private readonly EntityManagerInterface         $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $decls = $this->repo->findBy([], ['createdAt' => 'DESC']);
        $list  = array_map(fn($d) => $d->toArray(), $decls);
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(InsuranceDeclaration $decl): JsonResponse
    {
        return $this->json($decl->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent(), true);
        $decl  = new InsuranceDeclaration();
        $policy = $this->em->getReference(InsurancePolicy::class, (int) $data['policyId']);
        $decl->setPolicy($policy);
        $decl->setPeriodFrom(new \DateTime($data['periodFrom']));
        $decl->setPeriodTo(new \DateTime($data['periodTo']));
        $decl->setCurrency(strtoupper($data['currency']));
        $decl->setStatus('DRAFT');
        $decl->setCreatedAt(new \DateTime());
        $decl->setDeclarationRef($this->repo->generateDeclarationRef((int) $data['policyId']));

        // Attach certificates for the period
        $certs = $this->certRepo->findByPolicyAndPeriod(
            (int) $data['policyId'],
            $data['periodFrom'],
            $data['periodTo']
        );
        $totalInsured = 0.0;
        $totalPremium = 0.0;
        foreach ($certs as $cert) {
            $line = new InsuranceDeclarationLine();
            $line->setDeclaration($decl);
            $line->setCertificate($cert);
            $this->em->persist($line);
            $totalInsured += $cert->getInsuredAmount();
            $totalPremium += $cert->getPremiumAmount();
        }
        $decl->setCertificateCount(count($certs));
        $decl->setTotalInsuredValue($totalInsured);
        $decl->setTotalPremium($totalPremium);

        $this->repo->save($decl);
        return $this->json($decl->toArray(), 201);
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

    #[Route('/{id}/submit', methods: ['POST'])]
    public function submit(InsuranceDeclaration $decl): JsonResponse
    {
        $decl->setStatus('SUBMITTED');
        $decl->setSubmittedAt(new \DateTime());
        $this->repo->save($decl);
        return $this->json($decl->toArray());
    }

    #[Route('/{id}/acknowledge', methods: ['POST'])]
    public function acknowledge(InsuranceDeclaration $decl): JsonResponse
    {
        $decl->setStatus('ACKNOWLEDGED');
        $this->repo->save($decl);
        return $this->json($decl->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(InsuranceDeclaration $decl): JsonResponse
    {
        $this->repo->remove($decl);
        return $this->json(null, 204);
    }
}
