<?php
namespace App\Module\Integration\Controller;

use App\Module\Core\Entity\Port;

use App\Module\Operations\Repository\ShipmentMilestoneRepository;
use App\Module\Integration\Service\PortalShipmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/portal/shipments')]
#[IsGranted('ROLE_PORTAL_USER')]
#[AppModule('integration')]
class PortalShipmentController extends AbstractController
{
    public function __construct(
        private readonly PortalShipmentService       $service,
        private readonly ShipmentMilestoneRepository $milestoneRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] $user, Request $request): JsonResponse
    {
        /** @var PortalUser $user */
        $limit  = min((int) $request->query->get('limit', 20), 100);
        $offset = max((int) $request->query->get('offset', 0), 0);
        $shipments = $this->service->getShipmentsForClient($user->getClient(), $limit, $offset);

        return $this->json(array_map(fn($s) => [
            'id'            => $s->getId(),
            'code'          => $s->getCode(),
            'transportMode' => $s->getQuote()?->getTransportType()?->value,
            'status'        => $s->getStatus()?->value,
            'pol'           => $s->getBooking()?->getPortLoading()?->getCode(),
            'pod'           => $s->getBooking()?->getPortDischarge()?->getCode(),
            'etd'           => $s->getBooking()?->getEtd()?->format('Y-m-d'),
            'eta'           => $s->getBooking()?->getEta()?->format('Y-m-d'),
            'createdAt'     => $s->getCreatedDate()?->format(\DateTimeInterface::ATOM),
        ], $shipments));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function detail(#[CurrentUser] $user, int $id): JsonResponse
    {
        /** @var PortalUser $user */
        $shipment = $this->service->getShipmentForClient($id, $user->getClient());
        if (!$shipment) {
            throw $this->createNotFoundException();
        }

        $milestones = [];
        foreach ($this->milestoneRepository->findByShipment($shipment->getId()) as $m) {
            if (!$m->getMilestoneCode()?->isCustomerVisible()) {
                continue;
            }
            $milestones[] = [
                'milestoneCode' => $m->getMilestoneCode()->value,
                'label'         => $m->getMilestoneCode()->customerLabel(),
                'plannedDate'   => $m->getPlannedDate()?->format(\DateTimeInterface::ATOM),
                'actualDate'    => $m->getActualDate()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $this->json([
            'id'            => $shipment->getId(),
            'code'          => $shipment->getCode(),
            'transportMode' => $shipment->getQuote()?->getTransportType()?->value,
            'status'        => $shipment->getStatus()?->value,
            'pol'           => $shipment->getBooking()?->getPortLoading()?->getCode(),
            'pod'           => $shipment->getBooking()?->getPortDischarge()?->getCode(),
            'etd'           => $shipment->getBooking()?->getEtd()?->format('Y-m-d'),
            'eta'           => $shipment->getBooking()?->getEta()?->format('Y-m-d'),
            'milestones'    => $milestones,
        ]);
    }
}
