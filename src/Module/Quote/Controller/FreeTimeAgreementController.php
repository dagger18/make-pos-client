<?php
namespace App\Module\Quote\Controller;

use App\Module\Quote\Entity\FreeTimeAgreement;
use App\Module\Quote\Repository\FreeTimeAgreementRepository;
use App\Module\Core\Repository\PortRepository;
use App\Module\Carrier\Repository\ProviderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/free-time-agreement')]
#[IsGranted('ROLE_USER')]
#[AppModule('quote')]
class FreeTimeAgreementController extends AbstractController
{
    public function __construct(
        private readonly FreeTimeAgreementRepository $repository,
        private readonly ProviderRepository          $providerRepository,
        private readonly PortRepository              $portRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $carrierId = $request->query->get('carrierId');
        $entities  = $carrierId
            ? $this->repository->findByCarrier((int) $carrierId)
            : $this->repository->findAllOrdered();

        $list = array_map(fn($e) => $e->toArray(), $entities);
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $entity = $this->repository->find($id);
        if (!$entity) {
            return $this->json(['error' => $this->trans('Not found')], Response::HTTP_NOT_FOUND);
        }
        return $this->json($entity->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true) ?? [];
        $entity = $this->hydrate(new FreeTimeAgreement(), $data);
        $this->repository->save($entity);
        return $this->json($entity->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $entity = $this->repository->find($id);
        if (!$entity) {
            return $this->json(['error' => $this->trans('Not found')], Response::HTTP_NOT_FOUND);
        }
        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($entity, $data);
        $this->repository->save($entity);
        return $this->json($entity->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $entity = $this->repository->find($id);
        if (!$entity) {
            return $this->json(['error' => $this->trans('Not found')], Response::HTTP_NOT_FOUND);
        }
        $this->repository->remove($entity);
        return $this->json(null, Response::HTTP_NO_CONTENT);
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

    private function hydrate(FreeTimeAgreement $entity, array $data): FreeTimeAgreement
    {
        if (isset($data['carrierId'])) {
            $carrier = $this->providerRepository->find((int) $data['carrierId']);
            $entity->setCarrier($carrier);
        }
        if (array_key_exists('portId', $data)) {
            $port = $data['portId'] ? $this->portRepository->find((int) $data['portId']) : null;
            $entity->setPort($port);
        }
        if (isset($data['direction']))     $entity->setDirection($data['direction']);
        if (array_key_exists('containerType', $data)) $entity->setContainerType($data['containerType'] ?: null);
        if (isset($data['freeType']))      $entity->setFreeType($data['freeType']);
        if (isset($data['freeDays']))      $entity->setFreeDays((int) $data['freeDays']);
        if (isset($data['rateTiers']))     $entity->setRateTiers((array) $data['rateTiers']);
        if (isset($data['currency']))      $entity->setCurrency($data['currency']);
        if (isset($data['effectiveFrom'])) $entity->setEffectiveFrom(new \DateTime($data['effectiveFrom']));
        if (array_key_exists('effectiveTo', $data)) {
            $entity->setEffectiveTo($data['effectiveTo'] ? new \DateTime($data['effectiveTo']) : null);
        }
        return $entity;
    }
}
