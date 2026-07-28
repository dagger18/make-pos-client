<?php
namespace App\Module\Finance\Controller;

use App\Module\Core\Controller\CrudController;

use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PostActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Module\Finance\Repository\CurrencyRepository;
use App\Module\Core\Service\MasterSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/currency')]
#[IsGranted('ROLE_USER')]
#[AppModule('finance')]
class CurrencyController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;

    #[Route('/master-search', methods: ['GET'])]
    public function masterSearch(
        Request $request,
        MasterSyncService $masterSyncService,
    ): JsonResponse {
        $q = trim($request->query->get('q', ''));
        $limit = min((int) $request->query->get('limit', 20), 100);
        $offset = max((int) $request->query->get('offset', 0), 0);
        $excludeIds = array_filter(array_map('intval', explode(',', $request->query->get('excludeIds', ''))));

        $list = $masterSyncService->searchCurrencies($q, $limit, $offset, $excludeIds);

        return $this->json(['list' => $list]);
    }

    #[Route('/save-from-master', methods: ['POST'])]
    public function saveFromMaster(
        Request $request,
        CurrencyRepository $currencyRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $id = (int) ($data['id'] ?? 0);

        if (!$id) {
            return $this->json(['error' => $this->trans('id is required.')], Response::HTTP_BAD_REQUEST);
        }

        if ($currencyRepository->find($id)) {
            return $this->json(['id' => $id]);
        }

        $em->getConnection()->insert('currency', [
            'id'                => $id,
            'name'              => $data['name'] ?? '',
            'symbol'            => $data['symbol'] ?? '',
            'code'              => $data['code'] ?? '',
            'rate'              => $data['rate'] ?? null,
            'thousand_separator' => $data['thousandSeparator'] ?? null,
            'decimal_separator'  => $data['decimalSeparator'] ?? null,
            'decimal_places'     => $data['decimalPlaces'] ?? null,
        ]);

        return $this->json(['id' => $id], Response::HTTP_CREATED);
    }
}
