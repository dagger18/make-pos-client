<?php
namespace App\Module\Crm\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Core\Entity\Branch;
use App\Module\Core\Entity\User;
use App\Module\Crm\Entity\SalesTarget;
use App\Module\Crm\Repository\SalesTargetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/crm/sales-target')]
#[IsGranted('ROLE_USER')]
#[AppModule('crm')]
class SalesTargetController extends AbstractController
{
    public function __construct(
        private readonly SalesTargetRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $year = $request->query->get('year', date('Y'));
        $targets = $this->repo->findByYear((int) $year);
        return $this->json(array_map(fn($t) => $t->toArray(), $targets));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(SalesTarget $target): JsonResponse
    {
        return $this->json($target->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $target = $this->hydrate(new SalesTarget(), $data);
        $target->setCreatedAt(new \DateTime());
        $this->repo->save($target);
        return $this->json($target->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(SalesTarget $target, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($target, $data);
        $this->repo->save($target);
        return $this->json($target->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(SalesTarget $target): JsonResponse
    {
        $this->repo->remove($target);
        return $this->json(null, 204);
    }

    private function hydrate(SalesTarget $target, array $data): SalesTarget
    {
        $target->setSalesRep($this->em->getReference(User::class, (int) $data['salesRepId']));
        $target->setPeriodYear((int) $data['periodYear']);
        $target->setPeriodMonth(isset($data['periodMonth']) && $data['periodMonth'] !== null ? (int) $data['periodMonth'] : null);
        $target->setTargetType($data['targetType']);
        $target->setTargetValue((float) $data['targetValue']);
        $target->setCurrency($data['currency'] ?? null);

        $branchId = $data['branchId'] ?? null;
        $target->setBranch($branchId ? $this->em->getReference(Branch::class, (int) $branchId) : null);

        return $target;
    }
}
