<?php
namespace App\Module\Carrier\Controller;

use App\Module\Carrier\Entity\ContainerDdTracking;
use App\Module\Carrier\Repository\ContainerDdTrackingRepository;
use App\Module\Quote\Repository\FreeTimeAgreementRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Finance\Service\DdCalculatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dd')]
#[IsGranted('ROLE_USER')]
#[AppModule('carrier')]
class ContainerDdController extends AbstractController
{
    public function __construct(
        private readonly ContainerDdTrackingRepository $repository,
        private readonly ShipmentRepository            $shipmentRepository,
        private readonly FreeTimeAgreementRepository   $ftaRepository,
        private readonly DdCalculatorService           $calculator,
    ) {}

    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $records = $this->repository->findDashboard();
        return $this->json(array_map(fn($r) => $r->toArray(), $records));
    }

    #[Route('/shipment/{shipmentId}', methods: ['GET'], requirements: ['shipmentId' => '\d+'])]
    public function listByShipment(int $shipmentId): JsonResponse
    {
        $records = $this->repository->findByShipment($shipmentId);
        return $this->json(array_map(fn($r) => $r->toArray(), $records));
    }

    #[Route('/shipment/{shipmentId}', methods: ['POST'], requirements: ['shipmentId' => '\d+'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) {
            return $this->json(['error' => $this->trans('Shipment not found')], Response::HTTP_NOT_FOUND);
        }

        $data   = json_decode($request->getContent(), true) ?? [];
        $record = (new ContainerDdTracking())->setShipment($shipment);
        $this->hydrate($record, $data);
        $this->calculator->updateAccrual($record, new \DateTime('today'));
        $this->repository->save($record);

        return $this->json($record->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $record = $this->repository->find($id);
        if (!$record) {
            return $this->json(['error' => $this->trans('Not found')], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($record, $data);
        if (!$record->isFinal()) {
            $this->calculator->updateAccrual($record, new \DateTime('today'));
        }
        $this->repository->save($record);

        return $this->json($record->toArray());
    }

    #[Route('/{id}/return', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function recordReturn(int $id, Request $request): JsonResponse
    {
        $record = $this->repository->find($id);
        if (!$record) {
            return $this->json(['error' => $this->trans('Not found')], Response::HTTP_NOT_FOUND);
        }

        $data       = json_decode($request->getContent(), true) ?? [];
        $returnDate = isset($data['returnDate'])
            ? new \DateTime($data['returnDate'])
            : new \DateTime('today');

        $this->calculator->finalise($record, $returnDate);
        $this->repository->save($record);

        return $this->json($record->toArray());
    }

    #[Route('/{id}/dispute', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function dispute(int $id, Request $request): JsonResponse
    {
        $record = $this->repository->find($id);
        if (!$record) {
            return $this->json(['error' => $this->trans('Not found')], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $record->setIsDisputed(true);
        if (isset($data['reason'])) {
            $record->setDisputeReason($data['reason']);
        }
        $this->repository->save($record);

        return $this->json($record->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $record = $this->repository->find($id);
        if (!$record) {
            return $this->json(['error' => $this->trans('Not found')], Response::HTTP_NOT_FOUND);
        }
        $this->repository->remove($record);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(ContainerDdTracking $record, array $data): ContainerDdTracking
    {
        if (isset($data['containerNumber']))    $record->setContainerNumber($data['containerNumber']);
        if (isset($data['ddType']))             $record->setDdType($data['ddType']);
        if (isset($data['currency']))           $record->setCurrency($data['currency']);
        if (isset($data['freeDays']))           $record->setFreeDays((int) $data['freeDays']);
        if (isset($data['freeStartDate']))      $record->setFreeStartDate(new \DateTime($data['freeStartDate']));
        if (isset($data['freeEndDate']))        $record->setFreeEndDate(new \DateTime($data['freeEndDate']));
        if (array_key_exists('freeTimeAgreementId', $data)) {
            $fta = $data['freeTimeAgreementId']
                ? $this->ftaRepository->find((int) $data['freeTimeAgreementId'])
                : null;
            $record->setFreeTimeAgreement($fta);
        }
        return $record;
    }
}
