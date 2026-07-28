<?php

namespace App\Module\Operations\Controller;

use App\Module\Operations\Entity\WarehouseFacility;
use App\Module\Operations\Repository\WarehouseFacilityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/warehouse-facility')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class WarehouseFacilityController extends AbstractController
{
    public function __construct(
        private readonly WarehouseFacilityRepository $facilityRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $list = array_map(fn($f) => $this->serialize($f), $this->facilityRepository->findBy([], ['name' => 'ASC']));
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $facility = $this->facilityRepository->find($id);
        if (!$facility) throw $this->createNotFoundException();
        return $this->json($this->serialize($facility));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $facility = $this->hydrate(new WarehouseFacility(), $body);

        $errors = $this->validate($facility);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->facilityRepository->save($facility);
        return $this->json($this->serialize($facility), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $facility = $this->facilityRepository->find($id);
        if (!$facility) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($facility, $body);

        $errors = $this->validate($facility);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->facilityRepository->save($facility);
        return $this->json($this->serialize($facility));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $facility = $this->facilityRepository->find($id);
        if (!$facility) throw $this->createNotFoundException();

        $this->facilityRepository->delete($facility);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/inventory', methods: ['GET'])]
    public function inventory(int $id): JsonResponse
    {
        $facility = $this->facilityRepository->find($id);
        if (!$facility) throw $this->createNotFoundException();

        $conn = $this->facilityRepository->getEntityManager()->getConnection();
        $rows = $conn->fetchAllAssociative(
            'SELECT wr.id, wr.receipt_number, wr.shipment_id, wr.consol_id,
                    wr.pieces_received, wr.gross_weight_kg, wr.volume_cbm,
                    wr.condition_code, wr.storage_zone, wr.storage_location,
                    wr.received_at, wr.receipt_type
             FROM warehouse_receipt wr
             WHERE wr.facility_id = :fid AND wr.released_at IS NULL
             ORDER BY wr.storage_zone, wr.storage_location',
            ['fid' => $id]
        );
        return $this->json($rows);
    }

    private function hydrate(WarehouseFacility $f, array $body): WarehouseFacility
    {
        if (isset($body['name']))              $f->setName($body['name']);
        if (array_key_exists('locationCode', $body)) $f->setLocationCode($body['locationCode'] ?: null);
        if (array_key_exists('address', $body))      $f->setAddress($body['address'] ?: null);
        if (array_key_exists('totalAreaSqm', $body)) $f->setTotalAreaSqm($body['totalAreaSqm'] !== null ? (string) $body['totalAreaSqm'] : null);
        if (array_key_exists('reeferCapacity', $body)) $f->setReeferCapacity($body['reeferCapacity'] !== null ? (int) $body['reeferCapacity'] : null);
        if (isset($body['bonded']))            $f->setBonded((bool) $body['bonded']);
        if (isset($body['dangerousGoodsApproved'])) $f->setDangerousGoodsApproved((bool) $body['dangerousGoodsApproved']);
        if (array_key_exists('contactPhone', $body)) $f->setContactPhone($body['contactPhone'] ?: null);
        if (array_key_exists('contactEmail', $body)) $f->setContactEmail($body['contactEmail'] ?: null);
        if (isset($body['isActive']))          $f->setIsActive((bool) $body['isActive']);
        return $f;
    }

    private function validate(WarehouseFacility $f): array
    {
        $errors = [];
        if ($f->getName() === '') $errors[] = 'Name is required.';
        return $errors;
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

    private function serialize(WarehouseFacility $f): array
    {
        return [
            'id'                    => $f->getId(),
            'name'                  => $f->getName(),
            'locationCode'          => $f->getLocationCode(),
            'address'               => $f->getAddress(),
            'totalAreaSqm'          => $f->getTotalAreaSqm(),
            'reeferCapacity'        => $f->getReeferCapacity(),
            'bonded'                => $f->isBonded(),
            'dangerousGoodsApproved'=> $f->isDangerousGoodsApproved(),
            'contactPhone'          => $f->getContactPhone(),
            'contactEmail'          => $f->getContactEmail(),
            'isActive'              => $f->isActive(),
            'createdAt'             => $f->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
