<?php

namespace App\Module\Operations\Controller;

use App\Module\Operations\Entity\Consolidation;
use App\Module\Operations\Enum\ConsolidationStatus;
use App\Module\Core\Enum\Magnum;
use App\Module\Operations\Enum\ShipmentStatus;
use App\Module\Operations\Entity\ShipmentMilestone;
use App\Module\Carrier\Enum\MilestoneCode;
use App\Module\Core\Repository\BranchRepository;
use App\Module\Crm\Repository\ClientRepository;
use App\Module\Operations\Repository\ConsolidationRepository;
use App\Module\Core\Repository\PortRepository;
use App\Module\Carrier\Repository\ProviderRepository;
use App\Module\Operations\Repository\ShipmentMilestoneRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Core\Service\MediaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\LocaleSwitcher;

#[Route('/consolidation')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class ConsolidationController extends AbstractController
{
    public function __construct(
        private readonly ConsolidationRepository    $consolRepository,
        private readonly ShipmentRepository         $shipmentRepository,
        private readonly BranchRepository           $branchRepository,
        private readonly ClientRepository           $clientRepository,
        private readonly PortRepository             $portRepository,
        private readonly ShipmentMilestoneRepository $milestoneRepository,
        private readonly ProviderRepository         $providerRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'status'        => $request->query->get('status'),
            'transportMode' => $request->query->get('transportMode'),
        ];
        $items = $this->consolRepository->findByFilters(array_filter($filters));
        $list  = array_map(fn($c) => $this->serializeSummary($c), $items);
        return $this->json($this->paginate($list, $request));
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

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];

        if (empty($body['transportMode']) || empty($body['serviceType']) || empty($body['branchId'])) {
            return $this->json(['error' => $this->trans('transportMode, serviceType and branchId are required.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $branch = $this->branchRepository->find((int) $body['branchId']);
        if (!$branch) {
            return $this->json(['error' => $this->trans('Branch not found.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $consol = new Consolidation();
        $consol->setTransportMode($body['transportMode'])
               ->setServiceType($body['serviceType'])
               ->setBranch($branch)
               ->setApportionmentBasis($body['apportionmentBasis'] ?? 'WEIGHT')
               ->setCreatedBy($this->getUser());

        $this->hydrate($consol, $body);
        $consol->setCode($this->consolRepository->generateCode($body['transportMode']));
        $this->consolRepository->save($consol);

        return $this->json($this->serializeDetail($consol), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();
        return $this->json($this->serializeDetail($consol));
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();
        if ($consol->getStatus() === ConsolidationStatus::Cancelled) {
            return $this->json(['error' => $this->trans('Cannot edit a cancelled consolidation.')], Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($consol, $body);
        $this->consolRepository->save($consol);

        return $this->json($this->serializeDetail($consol));
    }

    #[Route('/{id}/close', methods: ['PATCH'])]
    public function close(int $id): JsonResponse
    {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();

        $children = $this->shipmentRepository->findBy(['consolId' => $id]);
        foreach ($children as $child) {
            if (in_array($child->getStatus(), [ShipmentStatus::Draft, ShipmentStatus::Cancelled])) {
                return $this->json([
                    'error' => $this->trans('Cannot close: child shipment ' . ($child->getCode() ?? $child->getId()) . ' is in ' . $child->getStatus()->value . ' status.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            // Write MBL/MAWB to each child
            $masterBill = $consol->getMblNumber() ?? $consol->getMawbNumber();
            if ($masterBill) {
                $child->setMasterBill($masterBill);
                $this->shipmentRepository->save($child);
            }
        }

        $consol->setStatus(ConsolidationStatus::Closed);
        $this->consolRepository->save($consol);

        return $this->json($this->serializeDetail($consol));
    }

    #[Route('/{id}/depart', methods: ['PATCH'])]
    public function depart(int $id): JsonResponse
    {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();

        if ($consol->getStatus() !== ConsolidationStatus::Closed) {
            return $this->json(['error' => $this->trans('Only a closed consolidation can be departed.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $consol->setStatus(ConsolidationStatus::Departed);
        $this->consolRepository->save($consol);

        $departureCode = match($consol->getTransportMode()) {
            'SEA'  => MilestoneCode::VesselDeparted,
            'AIR'  => MilestoneCode::FlightDeparted,
            default => null,
        };

        if ($departureCode !== null) {
            $children = $this->shipmentRepository->findBy(['consolId' => $id]);
            foreach ($children as $child) {
                $milestone = $this->milestoneRepository->findByShipmentAndCode($child->getId(), $departureCode)
                    ?? (new ShipmentMilestone())->setShipment($child)->setMilestoneCode($departureCode);
                if ($milestone->getActualDate() === null) {
                    $milestone->setActualDate(new \DateTime());
                    $milestone->setSource('CONSOL_AUTO');
                    $milestone->setUpdatedBy($this->getUser());
                    $milestone->recalculateException();
                    $this->milestoneRepository->save($milestone);
                }
            }
        }

        return $this->json($this->serializeDetail($consol));
    }

    #[Route('/{id}/arrive', methods: ['PATCH'])]
    public function arrive(int $id): JsonResponse
    {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();

        if ($consol->getStatus() !== ConsolidationStatus::Departed) {
            return $this->json(['error' => $this->trans('Only a departed consolidation can be marked as arrived.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $consol->setStatus(ConsolidationStatus::Arrived);
        $this->consolRepository->save($consol);

        $arrivalCode = match($consol->getTransportMode()) {
            'SEA'  => MilestoneCode::VesselArrived,
            'AIR'  => MilestoneCode::FlightArrived,
            default => null,
        };

        if ($arrivalCode !== null) {
            $children = $this->shipmentRepository->findBy(['consolId' => $id]);
            foreach ($children as $child) {
                $milestone = $this->milestoneRepository->findByShipmentAndCode($child->getId(), $arrivalCode)
                    ?? (new ShipmentMilestone())->setShipment($child)->setMilestoneCode($arrivalCode);
                if ($milestone->getActualDate() === null) {
                    $milestone->setActualDate(new \DateTime());
                    $milestone->setSource('CONSOL_AUTO');
                    $milestone->setUpdatedBy($this->getUser());
                    $milestone->recalculateException();
                    $this->milestoneRepository->save($milestone);
                }
            }
        }

        return $this->json($this->serializeDetail($consol));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function cancel(int $id): JsonResponse
    {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();

        $activeChildren = array_filter(
            $this->shipmentRepository->findBy(['consolId' => $id]),
            fn($s) => !in_array($s->getStatus(), [ShipmentStatus::Cancelled])
        );
        if (!empty($activeChildren)) {
            return $this->json(['error' => $this->trans('Remove all child shipments before cancelling.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $consol->setStatus(ConsolidationStatus::Cancelled);
        $this->consolRepository->save($consol);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/shipments', methods: ['POST'])]
    public function addShipment(int $id, Request $request): JsonResponse
    {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();
        if ($consol->getStatus() === ConsolidationStatus::Closed) {
            return $this->json(['error' => $this->trans('Cannot add shipments to a closed consolidation.')], Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $shipment = $this->shipmentRepository->find((int) ($body['shipmentId'] ?? 0));
        if (!$shipment) {
            return $this->json(['error' => $this->trans('Shipment not found.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($shipment->getConsolId() && $shipment->getConsolId() !== $id) {
            return $this->json(['error' => $this->trans('Shipment already belongs to another consolidation.')], Response::HTTP_CONFLICT);
        }

        $shipment->setConsolId($id);
        $this->shipmentRepository->save($shipment);

        return $this->json(['shipmentId' => $shipment->getId(), 'code' => $shipment->getCode()]);
    }

    #[Route('/{id}/shipments/{shipId}', methods: ['DELETE'])]
    public function removeShipment(int $id, int $shipId): JsonResponse
    {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();

        $shipment = $this->shipmentRepository->find($shipId);
        if (!$shipment || $shipment->getConsolId() !== $id) {
            throw $this->createNotFoundException();
        }

        $shipment->setConsolId(null);
        $this->shipmentRepository->save($shipment);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/manifest-pdf/{language}', methods: ['GET'])]
    public function manifestPdf(
        int $id, string $language,
        Request $request,
        MediaService $mediaService,
        RequestStack $requestStack
    ): StreamedResponse {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();

        $company  = $this->providerRepository->find(Magnum::COMPANY_PROVIDER_ID);
        $children = $this->shipmentRepository->findBy(['consolId' => $id]);

        $data = [
            'company'    => $company,
            'consol'     => $consol,
            'children'   => $children,
            'basePath'   => $request->getUriForPath(''),
            'filename'   => 'Manifest_' . $consol->getCode() . '_' . $language . '.pdf',
            'template'   => 'pdf/consolidation-manifest.html.twig',
            'renderMode' => 'pdf',
        ];

        $response = new StreamedResponse();
        $response->setCallback(function () use ($mediaService, $consol, $request, $requestStack, $data, $language): void {
            $requestStack->push($request);
            date_default_timezone_set($request->query->get('timezone', 'UTC'));
            $mediaService->generatePdf($consol, $data, $language);
        });
        return $response;
    }

    #[Route('/{id}/manifest-pdf-preview/{language}', methods: ['GET'])]
    public function manifestPdfPreview(
        int $id, string $language,
        Request $request,
        LocaleSwitcher $localeSwitcher
    ): Response {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();

        $company  = $this->providerRepository->find(Magnum::COMPANY_PROVIDER_ID);
        $children = $this->shipmentRepository->findBy(['consolId' => $id]);

        $data = [
            'company'    => $company,
            'consol'     => $consol,
            'children'   => $children,
            'basePath'   => $request->getUriForPath(''),
            'renderMode' => 'preview',
        ];

        date_default_timezone_set($request->query->get('timezone', 'UTC'));
        $localeSwitcher->setLocale($language);
        $view = $this->renderView('pdf/consolidation-manifest.html.twig', $data);
        $localeSwitcher->reset();
        date_default_timezone_set('UTC');
        return new Response($view);
    }

    private function hydrate(Consolidation $consol, array $body): void
    {
        if (isset($body['carrierId'])) {
            $consol->setCarrier($body['carrierId'] ? $this->clientRepository->find((int) $body['carrierId']) : null);
        }
        if (isset($body['polId'])) {
            $consol->setPol($body['polId'] ? $this->portRepository->find((int) $body['polId']) : null);
        }
        if (isset($body['podId'])) {
            $consol->setPod($body['podId'] ? $this->portRepository->find((int) $body['podId']) : null);
        }
        if (array_key_exists('vessel',          $body)) $consol->setVessel($body['vessel'] ?: null);
        if (array_key_exists('voyage',          $body)) $consol->setVoyage($body['voyage'] ?: null);
        if (array_key_exists('mblNumber',       $body)) $consol->setMblNumber($body['mblNumber'] ?: null);
        if (array_key_exists('flightNumber',    $body)) $consol->setFlightNumber($body['flightNumber'] ?: null);
        if (array_key_exists('mawbNumber',      $body)) $consol->setMawbNumber($body['mawbNumber'] ?: null);
        if (array_key_exists('containerNumber', $body)) $consol->setContainerNumber($body['containerNumber'] ?: null);
        if (array_key_exists('uldNumber',       $body)) $consol->setUldNumber($body['uldNumber'] ?: null);
        if (array_key_exists('etd',             $body)) $consol->setEtd($body['etd'] ? new \DateTime($body['etd']) : null);
        if (array_key_exists('eta',             $body)) $consol->setEta($body['eta'] ? new \DateTime($body['eta']) : null);
        if (array_key_exists('cfsCutoff',    $body)) $consol->setCfsCutoff($body['cfsCutoff'] ? new \DateTime($body['cfsCutoff']) : null);
        if (array_key_exists('docCutoff',    $body)) $consol->setDocCutoff($body['docCutoff'] ? new \DateTime($body['docCutoff']) : null);
        if (array_key_exists('maxWeightKg',  $body)) $consol->setMaxWeightKg($body['maxWeightKg'] !== null ? (string) $body['maxWeightKg'] : null);
        if (array_key_exists('maxVolumeCbm', $body)) $consol->setMaxVolumeCbm($body['maxVolumeCbm'] !== null ? (string) $body['maxVolumeCbm'] : null);
        if (isset($body['apportionmentBasis']))         $consol->setApportionmentBasis($body['apportionmentBasis']);
    }

    private function serializeSummary(Consolidation $c): array
    {
        $children = $this->shipmentRepository->findBy(['consolId' => $c->getId()]);
        return [
            'id'            => $c->getId(),
            'code'          => $c->getCode(),
            'transportMode' => $c->getTransportMode(),
            'serviceType'   => $c->getServiceType(),
            'status'        => $c->getStatus()->value,
            'statusLabel'   => $c->getStatus()->label(),
            'vessel'        => $c->getVessel(),
            'flightNumber'  => $c->getFlightNumber(),
            'pol'           => $c->getPol() ? ['id' => $c->getPol()->getId(), 'code' => $c->getPol()->getCode(), 'name' => $c->getPol()->getName()] : null,
            'pod'           => $c->getPod() ? ['id' => $c->getPod()->getId(), 'code' => $c->getPod()->getCode(), 'name' => $c->getPod()->getName()] : null,
            'etd'           => $c->getEtd()?->format('Y-m-d'),
            'eta'           => $c->getEta()?->format('Y-m-d'),
            'childCount'    => count($children),
        ];
    }

    private function serializeDetail(Consolidation $c): array
    {
        $children = $this->shipmentRepository->findBy(['consolId' => $c->getId()]);
        return array_merge($this->serializeSummary($c), [
            'voyage'           => $c->getVoyage(),
            'mblNumber'        => $c->getMblNumber(),
            'mawbNumber'       => $c->getMawbNumber(),
            'containerNumber'  => $c->getContainerNumber(),
            'uldNumber'        => $c->getUldNumber(),
            'apportionmentBasis' => $c->getApportionmentBasis(),
            'cfsCutoff'        => $c->getCfsCutoff()?->format('Y-m-d\TH:i:s'),
            'docCutoff'        => $c->getDocCutoff()?->format('Y-m-d\TH:i:s'),
            'maxWeightKg'      => $c->getMaxWeightKg(),
            'maxVolumeCbm'     => $c->getMaxVolumeCbm(),
            'createdBy'        => $c->getCreatedBy() ? ['id' => $c->getCreatedBy()->getId(), 'name' => $c->getCreatedBy()->getFullName()] : null,
            'carrier'          => $c->getCarrier() ? ['id' => $c->getCarrier()->getId(), 'name' => $c->getCarrier()->getName()] : null,
            'branch'           => $c->getBranch() ? ['id' => $c->getBranch()->getId(), 'name' => $c->getBranch()->getName()] : null,
            'children'         => array_map(fn($s) => [
                'id'     => $s->getId(),
                'code'   => $s->getCode(),
                'status' => $s->getStatus()->value,
                'client' => $s->getQuote()?->getClient()?->getName(),
            ], $children),
        ]);
    }
}
