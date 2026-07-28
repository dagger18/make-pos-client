<?php
declare(strict_types=1);
namespace App\Module\Compliance\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Compliance\Entity\SanctionsList;
use App\Module\Compliance\Repository\SanctionsListRepository;
use App\Module\Compliance\Service\AuditService;
use App\Module\Compliance\Service\SanctionsScreeningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compliance/sanctions')]
#[IsGranted('ROLE_USER')]
#[AppModule('compliance')]
class SanctionsController extends AbstractController
{
    public function __construct(
        private readonly SanctionsListRepository   $repo,
        private readonly SanctionsScreeningService $screeningService,
        private readonly AuditService              $auditService,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $q        = $request->query->get('q');
        $listName = $request->query->get('listName');

        if ($q) {
            $items = $this->repo->findByName($q, $listName ?: null);
        } else {
            $criteria = $listName ? ['listName' => $listName] : [];
            $items = $this->repo->findBy($criteria, ['listedName' => 'ASC']);
        }

        $list = array_map(fn($s) => $s->toArray(), $items);
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/check', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $result = $this->screeningService->check(
            $data['orgName'] ?? '',
            $data['countryCode'] ?? null,
        );

        if ($result['status'] === 'CONFIRMED_HIT') {
            $this->auditService->logFromRequest($request, 'COMPLIANCE.SANCTIONS_HIT', 'USER',
                objectRef: $data['orgName'] ?? '',
                actionDetail: ['result' => $result['status'], 'matches' => count($result['exactMatches'])]
            );
        }

        return $this->json($result);
    }

    #[Route('/{id}/clear', methods: ['POST'])]
    public function clear(SanctionsList $entry, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->auditService->logFromRequest(
            request:      $request,
            eventType:    'COMPLIANCE.SANCTIONS_CLEARED',
            objectType:   'sanctions_entry',
            objectId:     $entry->getId(),
            objectRef:    $entry->getListedName(),
            actionDetail: ['reason' => $data['reason'] ?? null, 'clearedBy' => $data['clearedBy'] ?? null],
        );

        return $this->json(['status' => 'CLEARED', 'entryId' => $entry->getId()]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(SanctionsList $entry): JsonResponse
    {
        return $this->json($entry->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent(), true);
        $entry = $this->hydrate(new SanctionsList(), $data);
        $this->repo->save($entry);
        return $this->json($entry->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(SanctionsList $entry, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($entry, $data);
        $this->repo->save($entry);
        return $this->json($entry->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(SanctionsList $entry): JsonResponse
    {
        $this->repo->remove($entry);
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

    private function hydrate(SanctionsList $sl, array $data): SanctionsList
    {
        $sl->setListName($data['listName']);
        $sl->setListedName($data['listedName']);
        $sl->setAliases($data['aliases'] ?? null);
        $sl->setCountryCode($data['countryCode'] ?? null);
        $sl->setEntityType($data['entityType'] ?? null);
        $sl->setPrograms($data['programs'] ?? null);
        $sl->setReason($data['reason'] ?? null);
        $sl->setListedDate(isset($data['listedDate']) && $data['listedDate'] ? new \DateTime($data['listedDate']) : null);
        $sl->setSourceRef($data['sourceRef'] ?? null);
        $sl->setIsActive((bool) ($data['isActive'] ?? true));
        $sl->setLastUpdated(new \DateTime($data['lastUpdated'] ?? 'now'));
        return $sl;
    }
}
