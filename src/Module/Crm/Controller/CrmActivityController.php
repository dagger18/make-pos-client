<?php
namespace App\Module\Crm\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Core\Entity\User;
use App\Module\Crm\Entity\Client;
use App\Module\Crm\Entity\CrmActivity;
use App\Module\Crm\Entity\CrmOpportunity;
use App\Module\Crm\Repository\CrmActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/crm/activity')]
#[IsGranted('ROLE_USER')]
#[AppModule('crm')]
class CrmActivityController extends AbstractController
{
    public function __construct(
        private readonly CrmActivityRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $oppId    = $request->query->get('opportunityId');
        $upcoming = $request->query->get('upcoming');

        if ($upcoming) {
            return $this->json(array_map(fn($a) => $a->toArray(), $this->repo->findUpcomingActions()));
        }
        if ($oppId) {
            return $this->json(array_map(fn($a) => $a->toArray(), $this->repo->findByOpportunity((int) $oppId)));
        }
        $items = $this->repo->findBy([], ['performedAt' => 'DESC']);
        $list  = array_map(fn($a) => $a->toArray(), $items);
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(CrmActivity $activity): JsonResponse
    {
        return $this->json($activity->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $data     = json_decode($request->getContent(), true);
        $activity = $this->hydrate(new CrmActivity(), $data);
        if (!isset($data['performedBy'])) {
            $activity->setPerformedBy($user);
        }
        if (!isset($data['performedAt'])) {
            $activity->setPerformedAt(new \DateTime());
        }
        $this->repo->save($activity);
        return $this->json($activity->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(CrmActivity $activity, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($activity, $data);
        $this->repo->save($activity);
        return $this->json($activity->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(CrmActivity $activity): JsonResponse
    {
        $this->repo->remove($activity);
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

    private function hydrate(CrmActivity $activity, array $data): CrmActivity
    {
        $activity->setActivityType($data['activityType']);
        $activity->setSubject($data['subject'] ?? null);
        $activity->setDescription($data['description'] ?? null);
        $activity->setOutcome($data['outcome'] ?? null);
        $activity->setNextAction($data['nextAction'] ?? null);
        $activity->setNextActionDate(isset($data['nextActionDate']) && $data['nextActionDate'] ? new \DateTime($data['nextActionDate']) : null);

        if (isset($data['performedAt'])) {
            $activity->setPerformedAt(new \DateTime($data['performedAt']));
        }

        $oppId    = $data['opportunityId'] ?? null;
        $clientId = $data['clientId'] ?? null;
        $perfById = $data['performedById'] ?? null;
        $activity->setOpportunity($oppId ? $this->em->getReference(CrmOpportunity::class, (int) $oppId) : null);
        $activity->setClient($clientId ? $this->em->getReference(Client::class, (int) $clientId) : null);
        $activity->setPerformedBy($perfById ? $this->em->getReference(User::class, (int) $perfById) : null);

        return $activity;
    }
}
