<?php
namespace App\Module\Core\Controller;

use App\Module\Core\Controller\CrudController;

use App\Module\Core\Entity\Port;
use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PostActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Module\Core\Repository\PortRepository;
use App\Module\Core\Service\MasterSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/port')]
#[IsGranted('ROLE_USER')]
#[AppModule('core')]
class PortController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;

    #[Route('/master-search', methods: ['GET'])]
    public function masterSearch(
        Request $request,
        PortRepository $portRepository,
        MasterSyncService $masterSyncService,
    ): JsonResponse {
        $q = trim($request->query->get('q', ''));
        $limit = min((int) $request->query->get('limit', 20), 50);
        $offset = max((int) $request->query->get('offset', 0), 0);
        $excludeIds = array_filter(array_map('intval', explode(',', $request->query->get('excludeIds', ''))));
        $portTypes = $request->query->get('portTypes');

        if (strlen($q) < 2) {
            return $this->json(['list' => []]);
        }

        $list = $masterSyncService->searchPorts($q, $limit, $offset, $excludeIds, $portTypes);

        return $this->json(['list' => $list]);
    }

    #[Route('/save-from-master', methods: ['POST'])]
    public function saveFromMaster(
        Request $request,
        PortRepository $portRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $id = (int) ($data['id'] ?? 0);

        if (!$id) {
            return $this->json(['error' => $this->trans('id is required.')], Response::HTTP_BAD_REQUEST);
        }

        $existing = $portRepository->find($id);
        if ($existing) {
            return $this->json(['id' => $existing->getId()]);
        }

        $em->getConnection()->insert('port', [
            'id'         => $id,
            'name'       => $data['name'] ?? '',
            'code'       => $data['code'] ?? '',
            'code_full'  => $data['codeFull'] ?? null,
            'subdiv'     => $data['subdiv'] ?? null,
            'location'   => $data['location'] ?? null,
            'port_types' => isset($data['portTypes']) ? implode(',', (array) $data['portTypes']) : null,
        ]);

        return $this->json(['id' => $id], Response::HTTP_CREATED);
    }
}
