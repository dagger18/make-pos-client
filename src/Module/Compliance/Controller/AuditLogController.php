<?php
declare(strict_types=1);
namespace App\Module\Compliance\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Compliance\Repository\SystemAuditLogRepository;
use App\Module\Compliance\Service\AuditService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compliance/audit-log')]
#[IsGranted('ROLE_USER')]
#[AppModule('compliance')]
class AuditLogController extends AbstractController
{
    public function __construct(
        private readonly SystemAuditLogRepository $repo,
        private readonly AuditService             $auditService,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $from = $request->query->get('from') ? new \DateTime($request->query->get('from')) : null;
        $to   = $request->query->get('to')   ? new \DateTime($request->query->get('to'))   : null;

        $records = $this->repo->search(
            eventType:  $request->query->get('eventType'),
            actorEmail: $request->query->get('actorEmail'),
            actorId:    $request->query->getInt('actorId') ?: null,
            objectType: $request->query->get('objectType'),
            objectId:   $request->query->getInt('objectId') ?: null,
            result:     $request->query->get('result'),
            from:       $from,
            to:         $to,
            limit:      min((int) ($request->query->get('limit', 200)), 500),
        );

        return $this->json(array_map(fn($r) => $r->toArray(), $records));
    }

    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        return $this->json([
            'totals'           => $this->repo->getTotals(),
            'complianceStats'  => $this->repo->getComplianceEventStats(12),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent(), true);
        $entry = $this->auditService->logFromRequest(
            request:      $request,
            eventType:    $data['eventType'],
            actorType:    $data['actorType'] ?? 'USER',
            actorId:      isset($data['actorId']) ? (int) $data['actorId'] : null,
            actorEmail:   $data['actorEmail'] ?? null,
            objectType:   $data['objectType'] ?? null,
            objectId:     isset($data['objectId']) ? (int) $data['objectId'] : null,
            objectRef:    $data['objectRef'] ?? null,
            actionDetail: $data['actionDetail'] ?? null,
            result:       $data['result'] ?? 'SUCCESS',
        );

        return $this->json($entry->toArray(), 201);
    }
}
