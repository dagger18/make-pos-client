<?php
declare(strict_types=1);
namespace App\Module\Compliance\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Compliance\Entity\DataRetentionPolicy;
use App\Module\Compliance\Repository\DataRetentionPolicyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compliance/retention')]
#[IsGranted('ROLE_USER')]
#[AppModule('compliance')]
class DataRetentionController extends AbstractController
{
    public function __construct(private readonly DataRetentionPolicyRepository $repo) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $items = $this->repo->findBy([], ['dataCategory' => 'ASC']);
        $list  = array_map(fn($p) => $p->toArray(), $items);
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(DataRetentionPolicy $policy): JsonResponse
    {
        return $this->json($policy->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $policy = $this->hydrate(new DataRetentionPolicy(), $data);
        $policy->setCreatedAt(new \DateTime());
        $this->repo->save($policy);
        return $this->json($policy->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(DataRetentionPolicy $policy, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($policy, $data);
        $this->repo->save($policy);
        return $this->json($policy->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(DataRetentionPolicy $policy): JsonResponse
    {
        $this->repo->remove($policy);
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

    private function hydrate(DataRetentionPolicy $p, array $data): DataRetentionPolicy
    {
        $p->setDataCategory($data['dataCategory']);
        $p->setRetentionYears((int) $data['retentionYears']);
        $p->setLegalBasis($data['legalBasis']);
        $p->setAppliesTo($data['appliesTo']);
        $p->setAutoDelete((bool) ($data['autoDelete'] ?? false));
        return $p;
    }
}
