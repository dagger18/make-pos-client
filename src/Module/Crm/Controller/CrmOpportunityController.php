<?php
namespace App\Module\Crm\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Core\Entity\User;
use App\Module\Crm\Entity\Client;
use App\Module\Crm\Entity\CrmLead;
use App\Module\Crm\Entity\CrmOpportunity;
use App\Module\Crm\Repository\CrmOpportunityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/crm/opportunity')]
#[IsGranted('ROLE_USER')]
#[AppModule('crm')]
class CrmOpportunityController extends AbstractController
{
    public function __construct(
        private readonly CrmOpportunityRepository $repo,
        private readonly EntityManagerInterface   $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $stage      = $request->query->get('stage');
        $assigneeId = $request->query->get('assigneeId');
        $pipeline   = $request->query->get('pipeline');

        if ($pipeline) {
            $items = $this->repo->findPipeline($assigneeId ? (int) $assigneeId : null);
            return $this->json(array_map(fn($o) => $o->toArray(), $items));
        }

        $qb = $this->em->createQueryBuilder()
            ->select('o')->from(CrmOpportunity::class, 'o')
            ->orderBy('o.createdAt', 'DESC');

        if ($stage) {
            $qb->andWhere('o.stage = :stage')->setParameter('stage', $stage);
        }
        if ($assigneeId) {
            $qb->andWhere('IDENTITY(o.assignedTo) = :aid')->setParameter('aid', (int) $assigneeId);
        }
        $list = array_map(fn($o) => $o->toArray(), $qb->getQuery()->getResult());
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/pipeline-summary', methods: ['GET'])]
    public function pipelineSummary(): JsonResponse
    {
        return $this->json($this->repo->getPipelineSummary());
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(CrmOpportunity $opp): JsonResponse
    {
        return $this->json($opp->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $opp  = $this->hydrate(new CrmOpportunity(), $data);
        $opp->setCreatedAt(new \DateTime());
        $this->repo->save($opp);
        return $this->json($opp->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(CrmOpportunity $opp, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($opp, $data);
        $opp->setUpdatedAt(new \DateTime());
        $this->repo->save($opp);
        return $this->json($opp->toArray());
    }

    #[Route('/{id}/close', methods: ['POST'])]
    public function close(CrmOpportunity $opp, Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $stage  = $data['stage'] ?? 'CLOSED_LOST';
        $opp->setStage($stage);
        $opp->setProbabilityPct($stage === 'CLOSED_WON' ? 100 : 0);
        $opp->setLossReason($data['lossReason'] ?? null);
        $opp->setWinReason($data['winReason'] ?? null);
        $opp->setClosedAt(new \DateTime());
        $opp->setUpdatedAt(new \DateTime());
        $this->repo->save($opp);
        return $this->json($opp->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(CrmOpportunity $opp): JsonResponse
    {
        $this->repo->remove($opp);
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

    private function hydrate(CrmOpportunity $opp, array $data): CrmOpportunity
    {
        $opp->setTitle($data['title']);
        $opp->setTransportMode($data['transportMode'] ?? null);
        $opp->setServiceType($data['serviceType'] ?? null);
        $opp->setPolName($data['polName'] ?? null);
        $opp->setPodName($data['podName'] ?? null);
        $opp->setEstimatedVolume(isset($data['estimatedVolume']) ? (float) $data['estimatedVolume'] : null);
        $opp->setVolumeUom($data['volumeUom'] ?? null);
        $opp->setEstimatedRevenue(isset($data['estimatedRevenue']) ? (float) $data['estimatedRevenue'] : null);
        $opp->setCurrency($data['currency'] ?? null);
        $opp->setStage($data['stage'] ?? 'PROSPECTING');
        $opp->setProbabilityPct((int) ($data['probabilityPct'] ?? 10));
        $opp->setExpectedClose(isset($data['expectedClose']) && $data['expectedClose'] ? new \DateTime($data['expectedClose']) : null);
        $opp->setCompetitor($data['competitor'] ?? null);
        $opp->setLossReason($data['lossReason'] ?? null);
        $opp->setWinReason($data['winReason'] ?? null);
        $opp->setQuoteId($data['quoteId'] ?? null);
        $opp->setNotes($data['notes'] ?? null);

        $leadId   = $data['leadId'] ?? null;
        $clientId = $data['clientId'] ?? null;
        $assigneeId = $data['assignedToId'] ?? null;
        $opp->setLead($leadId ? $this->em->getReference(CrmLead::class, (int) $leadId) : null);
        $opp->setClient($clientId ? $this->em->getReference(Client::class, (int) $clientId) : null);
        $opp->setAssignedTo($assigneeId ? $this->em->getReference(User::class, (int) $assigneeId) : null);

        return $opp;
    }
}
