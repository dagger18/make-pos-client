<?php
namespace App\Module\Shift\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Shift\Entity\Shift;
use App\Module\Shift\Enum\ShiftStatus;
use App\Module\Shift\Repository\ShiftRepository;
use App\Module\Shift\Service\ShiftService;
use App\Module\Core\Controller\CrudController;
use App\Module\Core\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shift')]
#[IsGranted('ROLE_USER')]
#[AppModule('shift')]
class ShiftController extends CrudController
{
    public function __construct(
        BaseService $baseService,
        private readonly ShiftRepository $repo,
        private readonly ShiftService $shiftService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($baseService);
    }

    #[Route('', methods: ['GET'])]
    public function LIST(Request $request): JsonResponse
    {
        $query    = $request->query;
        $location = $this->getUser()->getLocation();

        return $this->json($this->repo->getList($query->all(), 'list', function (QueryBuilder $qb) use ($location, $query): void {
            if ($location) {
                $qb->andWhere('Shift.location = :location')
                   ->setParameter('location', $location);
            }
            if ($status = $query->get('status')) {
                $qb->andWhere('Shift.status = :status')
                   ->setParameter('status', $status);
            }
        }));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function DETAIL(int $id): JsonResponse
    {
        $shift = $this->repo->find($id);
        if (!$shift) {
            return $this->json(['error' => 'Not found.'], 404);
        }
        return $this->json($shift->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function POST(Request $request): JsonResponse
    {
        $user     = $this->getUser();
        $location = $user->getLocation();
        if (!$location) {
            return $this->json(['error' => 'User has no location assigned.'], 400);
        }

        $existing = $this->repo->findOneBy(['location' => $location, 'status' => ShiftStatus::Open]);
        if ($existing) {
            return $this->json([
                'error' => 'A shift is already open at this location.',
                'shift' => $existing->toArray(),
            ], 409);
        }

        $data  = $request->toArray();
        $shift = new Shift();
        $shift->setLocation($location);
        $shift->setCashier($user);
        $shift->setOpeningAmount($data['opening_amount'] ?? '0');
        if (!empty($data['notes'])) {
            $shift->setNotes($data['notes']);
        }

        $this->em->persist($shift);
        $this->em->flush();

        return $this->json($shift->toArray(), 201);
    }

    #[Route('/{id}/close', methods: ['POST'])]
    public function CLOSE(int $id, Request $request): JsonResponse
    {
        $shift = $this->repo->find($id);
        if (!$shift) {
            return $this->json(['error' => 'Not found.'], 404);
        }
        if ($shift->getStatus() !== ShiftStatus::Open) {
            return $this->json(['error' => 'Shift is already closed.'], 400);
        }

        $data = $request->toArray();
        $this->shiftService->close(
            $shift,
            $data['closing_amount'] ?? '0',
            $data['notes'] ?? null,
        );

        $this->em->flush();

        return $this->json($shift->toArray());
    }
}
