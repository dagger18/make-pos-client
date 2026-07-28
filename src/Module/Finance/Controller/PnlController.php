<?php
namespace App\Module\Finance\Controller;

use App\Module\Core\Entity\User;
use App\Module\Finance\Repository\PnlRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report')]
#[IsGranted('ROLE_USER')]
#[AppModule('finance')]
class PnlController extends AbstractController
{
    public function __construct(private readonly PnlRepository $repo) {}

    #[Route('/profit-loss', methods: ['GET'])]
    public function periodPnl(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getPeriodPnl($from, $to));
    }

    #[Route('/profit-loss/department', methods: ['GET'])]
    public function departmentPnl(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getDepartmentPnl($from, $to));
    }

    #[Route('/cost-sheet/{shipmentId}', methods: ['GET'])]
    public function costSheet(int $shipmentId, Request $request): JsonResponse
    {
        $visibleToFilter = null;

        if ($request->query->getBoolean('myDept')) {
            /** @var User $user */
            $user  = $this->getUser();
            $roles = $user->getRoles();

            $isPrivileged = (bool) array_intersect(['ROLE_MANAGER', 'ROLE_ADMIN'], $roles);

            if (!$isPrivileged) {
                $allowed = [];
                $showAll = false;

                foreach ($user->getDepartments() as $dept) {
                    $dir = $dept->getDirection();
                    if ($dir === null) {
                        $showAll = true;
                        break;
                    }
                    $mapped = match ($dir->value) {
                        'EXP' => 'ORIGIN',
                        'IMP' => 'DESTINATION',
                        default => null,
                    };
                    if ($mapped === null) {
                        $showAll = true;
                        break;
                    }
                    $allowed[] = $mapped;
                }

                if (!$showAll) {
                    $visibleToFilter = array_unique($allowed);
                }
            }
        }

        return $this->json($this->repo->getJobCostSheet($shipmentId, $visibleToFilter));
    }
}
