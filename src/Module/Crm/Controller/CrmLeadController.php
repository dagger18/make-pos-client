<?php
namespace App\Module\Crm\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Core\Entity\User;
use App\Module\Crm\Entity\Client;
use App\Module\Crm\Entity\CrmLead;
use App\Module\Crm\Repository\CrmLeadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/crm/lead')]
#[IsGranted('ROLE_USER')]
#[AppModule('crm')]
class CrmLeadController extends AbstractController
{
    public function __construct(
        private readonly CrmLeadRepository     $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status     = $request->query->get('status');
        $assigneeId = $request->query->get('assigneeId');

        $qb = $this->em->createQueryBuilder()
            ->select('l')->from(CrmLead::class, 'l')
            ->orderBy('l.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('l.status = :status')->setParameter('status', $status);
        }
        if ($assigneeId) {
            $qb->andWhere('IDENTITY(l.assignedTo) = :aid')->setParameter('aid', (int) $assigneeId);
        }
        $list = array_map(fn($l) => $l->toArray(), $qb->getQuery()->getResult());
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(CrmLead $lead): JsonResponse
    {
        return $this->json($lead->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $lead = $this->hydrate(new CrmLead(), $data);
        $lead->setCreatedBy($user);
        $lead->setCreatedAt(new \DateTime());
        $this->repo->save($lead);
        return $this->json($lead->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(CrmLead $lead, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($lead, $data);
        $this->repo->save($lead);
        return $this->json($lead->toArray());
    }

    #[Route('/{id}/convert', methods: ['POST'])]
    public function convert(CrmLead $lead, Request $request): JsonResponse
    {
        $data     = json_decode($request->getContent(), true);
        $clientId = $data['clientId'] ?? null;
        if ($clientId) {
            $lead->setConvertedClient($this->em->getReference(Client::class, (int) $clientId));
        }
        $lead->setStatus('CONVERTED');
        $lead->setConvertedAt(new \DateTime());
        $this->repo->save($lead);
        return $this->json($lead->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(CrmLead $lead): JsonResponse
    {
        $this->repo->remove($lead);
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

    private function hydrate(CrmLead $lead, array $data): CrmLead
    {
        $lead->setCompanyName($data['companyName']);
        $lead->setContactName($data['contactName'] ?? null);
        $lead->setContactEmail($data['contactEmail'] ?? null);
        $lead->setContactPhone($data['contactPhone'] ?? null);
        $lead->setCountryCode($data['countryCode'] ?? null);
        $lead->setIndustry($data['industry'] ?? null);
        $lead->setEstimatedVolume($data['estimatedVolume'] ?? null);
        $lead->setPrimaryMode($data['primaryMode'] ?? null);
        $lead->setPrimaryTrade($data['primaryTrade'] ?? null);
        $lead->setSource($data['source'] ?? null);
        $lead->setStatus($data['status'] ?? 'NEW');
        $lead->setNotes($data['notes'] ?? null);

        $assigneeId = $data['assignedToId'] ?? null;
        $lead->setAssignedTo($assigneeId ? $this->em->getReference(User::class, (int) $assigneeId) : null);

        return $lead;
    }
}
